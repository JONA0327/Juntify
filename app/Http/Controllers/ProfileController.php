<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\GoogleToken;
use App\Models\Plan;
use App\Services\GoogleDriveService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleTokenRefreshService;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Services\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(GoogleDriveService $drive, GoogleAuthController $auth, GoogleTokenRefreshService $tokenService)
    {
        $user = Auth::user();
        $planService = app(PlanLimitService::class);
        $driveLocked = !$planService->userCanUseDrive($user);
        $tempRetentionDays = $planService->getTemporaryRetentionDays($user);

        // Usar el nuevo servicio para verificar y renovar automáticamente el token
        $connectionStatus = $tokenService->checkConnectionStatus($user);

        $token = GoogleToken::where('username', $user->username)->first();
        $lastSync = optional($token)->updated_at;
        $subfolders = collect();
        $folderMessage = null;

        $folder = null;
        if ($token && $token->recordings_folder_id) {
            $folder = Folder::where('google_token_id', $token->id)
                           ->where('google_id', $token->recordings_folder_id)
                           ->first();
            if ($folder) {
                $subfolders = Subfolder::where('folder_id', $folder->id)->get();
            }
        }

        // Si no hay conexión válida
        if (!$connectionStatus['drive_connected'] && !$connectionStatus['calendar_connected']) {
            $driveConnected = false;
            $calendarConnected = false;
            $folderMessage = $connectionStatus['needs_reconnection']
                ? 'Token expirado. Se intentó renovar automáticamente pero falló. Necesitas reconectarte.'
                : $connectionStatus['message'];

            // Obtener planes para la sección de suscripciones
            $plans = Plan::where('is_active', true)->orderBy('price')->get();

            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage', 'plans', 'driveLocked', 'tempRetentionDays'));
        }

        $driveConnected = $connectionStatus['drive_connected'];
        $calendarConnected = $connectionStatus['calendar_connected'];

        // Si hay token válido, obtener información de la carpeta
        if ($token && $token->recordings_folder_id) {
            try {
                $client = $drive->getClient();

                // Usar el método del modelo para obtener el token como array completo
                $tokenArray = $token->getTokenArray();
                if (empty($tokenArray['access_token'])) {
                    throw new \Exception("Token inválido");
                }

                $client->setAccessToken($tokenArray);
                // Asegurar que el servicio compartido de Drive también tenga token (para crear carpetas fallback)
                try {
                    if (method_exists($drive, 'setAccessToken')) {
                        $drive->setAccessToken($tokenArray);
                    }
                } catch (\Throwable $eSet) {
                    Log::debug('No se pudo establecer access token en GoogleDriveService', [
                        'error' => $eSet->getMessage(),
                    ]);
                }

                // Nota: includeItemsFromAllDrives no es válido en files->get y causaba '(get) unknown parameter' en algunas libs
                $file = $drive->getDrive()->files->get(
                    $token->recordings_folder_id,
                    [
                        'fields' => 'id,name,parents',
                        'supportsAllDrives' => true,
                    ]
                );
                $folderName = $file->getName() ?? "recordings_{$user->username}";

                $folder = Folder::updateOrCreate(
                    [
                        'google_token_id' => $token->id,
                        'google_id'       => $token->recordings_folder_id,
                    ],
                    [
                        'name'      => $folderName,
                        'parent_id' => null,
                    ]
                );

                $subfolders = Subfolder::where('folder_id', $folder->id)->get();

                // Asegurar subcarpetas default si faltan
                try {
                    $expected = collect(config('drive.default_subfolders', []));
                    if ($expected->count()) {
                        $have = $subfolders->pluck('name')->map(fn($n) => mb_strtolower($n))->all();
                        $missing = $expected->filter(fn($name) => !in_array(mb_strtolower($name), $have));
                        if ($missing->count()) {
                            Log::info('Creando subcarpetas faltantes (ProfileController flujo token directo)', [
                                'missing' => $missing->values(),
                                'root_folder_id' => $folder->google_id,
                                'token_id' => $token->id,
                            ]);
                            try {
                                $sa = app(\App\Services\GoogleServiceAccount::class);
                            } catch (\Throwable $eSa) {
                                Log::warning('No se pudo inicializar ServiceAccount para crear subcarpetas faltantes', [
                                    'error' => $eSa->getMessage(),
                                ]);
                                $sa = null;
                            }
                            foreach ($missing as $name) {
                                $newId = null;
                                // Intentar en este orden: Service Account directa -> Service Account impersonada -> OAuth token
                                try {
                                    if ($sa) {
                                        $newId = $sa->createFolder($name, $folder->google_id);
                                    }
                                } catch (\Throwable $eSaDirect) {
                                    Log::debug('Fallo SA directa creando subcarpeta, intentando impersonación', [
                                        'name' => $name,
                                        'error' => $eSaDirect->getMessage(),
                                    ]);
                                    // Impersonar y reintentar si hay email
                                    if ($sa && $user->email) {
                                        try {
                                            $sa->impersonate($user->email);
                                            $newId = $sa->createFolder($name, $folder->google_id);
                                        } catch (\Throwable $eSaImp) {
                                            Log::debug('Fallo SA impersonada, fallback a OAuth', [
                                                'name' => $name,
                                                'error' => $eSaImp->getMessage(),
                                            ]);
                                        } finally {
                                            try { $sa->impersonate(null); } catch (\Throwable $eReset) { /* ignore */ }
                                        }
                                    }
                                }
                                if (!$newId) {
                                    try {
                                        $newId = $drive->createFolder($name, $folder->google_id);
                                    } catch (\Throwable $eOauth) {
                                        Log::warning('No se pudo crear subcarpeta (todas las estrategias fallaron)', [
                                            'name' => $name,
                                            'error' => $eOauth->getMessage(),
                                        ]);
                                    }
                                }
                                if ($newId) {
                                    try {
                                        if ($sa && $user->email) {
                                            try { $sa->shareItem($newId, $user->email, 'writer'); } catch (\Throwable $eShare) { /* ignore */ }
                                        }
                                        $model = Subfolder::firstOrCreate([
                                            'folder_id' => $folder->id,
                                            'google_id' => $newId,
                                        ], ['name' => $name]);
                                        $subfolders->push($model);
                                    } catch (\Throwable $ePersist) {
                                        Log::warning('Fallo persistiendo subcarpeta creada', [
                                            'name' => $name,
                                            'error' => $ePersist->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $eEnsure) {
                    Log::warning('Error asegurando subcarpetas default', [
                        'error' => $eEnsure->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Fallback: try with Service Account to fetch info and share with the user
                Log::warning('Fallo acceso carpeta raíz con token OAuth, intentando ServiceAccount', [
                    'token_id' => $token->id ?? null,
                    'folder_id' => $token->recordings_folder_id ?? null,
                    'error' => $e->getMessage(),
                ]);
                try {
                    $sa = app(\App\Services\GoogleServiceAccount::class);

                    try {
                        if ($user->email) {
                            $sa->impersonate($user->email);
                        }

                        $file = $sa->getDrive()->files->get(
                            $token->recordings_folder_id,
                            [
                                'fields' => 'name',
                                'supportsAllDrives' => true,
                            ]
                        );
                        $folderName = $file?->getName() ?: null;
                        if ($user->email) {
                            $sa->shareItem($token->recordings_folder_id, $user->email, 'writer');
                        }
                        if ($folderName) {
                            $folder = Folder::updateOrCreate(
                                [
                                    'google_token_id' => $token->id,
                                    'google_id'       => $token->recordings_folder_id,
                                ],
                                [
                                    'name'      => $folderName,
                                    'parent_id' => null,
                                ]
                            );
                            $subfolders = Subfolder::where('folder_id', $folder->id)->get();
                            $folderMessage = null;
                            // Asegurar subcarpetas faltantes también en este flujo
                            try {
                                $expected = collect(config('drive.default_subfolders', []));
                                $have = $subfolders->pluck('name')->map(fn($n) => mb_strtolower($n))->all();
                                $missing = $expected->filter(fn($name) => !in_array(mb_strtolower($name), $have));
                                if ($missing->count()) {
                                    foreach ($missing as $name) {
                                        $newId = null;
                                        try { $newId = $sa->createFolder($name, $folder->google_id); } catch (\Throwable $mf) {
                                            Log::debug('Fallo SA directa en fallback, intentando impersonación', [ 'name' => $name, 'error' => $mf->getMessage() ]);
                                            if ($user->email) {
                                                try { $sa->impersonate($user->email); $newId = $sa->createFolder($name, $folder->google_id); } catch (\Throwable $mf2) {
                                                    Log::debug('Fallo SA impersonada en fallback, intentando OAuth', [ 'name' => $name, 'error' => $mf2->getMessage() ]);
                                                } finally { try { $sa->impersonate(null); } catch (\Throwable $eR) { /* ignore */ } }
                                            }
                                        }
                                        if (!$newId) {
                                            try { if (method_exists($drive, 'createFolder')) { $newId = $drive->createFolder($name, $folder->google_id); } } catch (\Throwable $mf3) {
                                                Log::warning('Fallo total creando subcarpeta en fallback', [ 'name' => $name, 'error' => $mf3->getMessage() ]);
                                            }
                                        }
                                        if ($newId) {
                                            try {
                                                if ($user->email) { try { $sa->shareItem($newId, $user->email, 'writer'); } catch (\Throwable $se) { /* ignore */ } }
                                                $model = Subfolder::firstOrCreate([
                                                    'folder_id' => $folder->id,
                                                    'google_id' => $newId,
                                                ], ['name' => $name]);
                                                $subfolders->push($model);
                                            } catch (\Throwable $persistE) {
                                                Log::warning('Fallo persistiendo subcarpeta en fallback', [ 'name' => $name, 'error' => $persistE->getMessage() ]);
                                            }
                                        }
                                    }
                                }
                            } catch (\Throwable $eMissing) {
                                Log::warning('Error asegurando subcarpetas default en fallback', [
                                    'error' => $eMissing->getMessage(),
                                ]);
                            }
                        } else {
                            $folderMessage = 'No se pudo acceder a la carpeta principal. El token se renovó automáticamente pero hay problemas de permisos.';
                        }
                    } finally {
                        $sa->impersonate(null);
                    }
                } catch (\Throwable $e2) {
                    Log::error('ServiceAccount fallback también falló', [
                        'token_id' => $token->id ?? null,
                        'folder_id' => $token->recordings_folder_id ?? null,
                        'error_primary' => $e->getMessage(),
                        'error_fallback' => $e2->getMessage(),
                    ]);
                    $folderMessage = 'No se pudo acceder a la carpeta principal. El token se renovó automáticamente pero hay problemas de permisos.';
                }
            }
        }

        // Obtener planes para la sección de suscripciones
        $plans = Plan::where('is_active', true)->orderBy('price')->get();

            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage', 'plans', 'driveLocked', 'tempRetentionDays'));
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
