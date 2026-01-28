<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\GoogleToken;
use App\Models\Plan;
use App\Models\Payment;
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
            $plans = Plan::where('is_active', true)
                ->orderByRaw('COALESCE(monthly_price, price) ASC')
                ->get();

            // Obtener historial de pagos del usuario
            $userPayments = Payment::where('user_id', $user->id)
                                 ->orderBy('created_at', 'desc')
                                 ->take(10)
                                 ->get();

            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage', 'plans', 'driveLocked', 'tempRetentionDays', 'userPayments'));
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
        $plans = Plan::where('is_active', true)
            ->orderByRaw('COALESCE(monthly_price, price) ASC')
            ->get();

        // Obtener historial de pagos del usuario
        $userPayments = Payment::where('user_id', $user->id)
                             ->orderBy('created_at', 'desc')
                             ->take(10)
                             ->get();

            return view('profile', compact('user', 'driveConnected', 'calendarConnected', 'folder', 'subfolders', 'lastSync', 'folderMessage', 'plans', 'driveLocked', 'tempRetentionDays', 'userPayments'));
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
     * Update the user's language preference.
     */
    public function updateLanguage(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:es,en'],
        ]);

        $locale = $validated['locale'];
        $user = $request->user();
        $user->locale = $locale;
        $user->save();

        app()->setLocale($locale);

        // Si es una petición AJAX, devolver JSON
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'locale' => $locale
            ])->withCookie(cookie('locale', $locale, 60 * 24 * 365));
        }

        // Si es una petición normal, redirigir
        return Redirect::back()
            ->with('status', 'language-updated')
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365));
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

    /**
     * Download payment receipt
     */
    public function downloadReceipt(Request $request, Payment $payment)
    {
        // Verificar que el pago pertenece al usuario autenticado
        if ($payment->user_id !== $request->user()->id) {
            abort(403, __('profile.receipt.forbidden'));
        }

        // Verificar que el pago esté aprobado
        if ($payment->status !== 'approved') {
            abort(404, __('profile.receipt.not_available'));
        }

        // Crear contenido del recibo (simple HTML para este ejemplo)
        $receiptContent = $this->generateReceiptContent($payment);

        // Crear respuesta de descarga
        $filename = "recibo-pago-{$payment->id}.html";

        return response($receiptContent, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate receipt content
     */
    private function generateReceiptContent(Payment $payment)
    {
        $user = $payment->user;
        $createdAt = Carbon::parse($payment->created_at)->format('d/m/Y H:i:s');
        $locale = app()->getLocale();

        return "
        <!DOCTYPE html>
        <html lang='{$locale}'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . __('profile.receipt.title') . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #3b82f6; padding-bottom: 20px; }
                .logo { font-size: 28px; font-weight: bold; color: #3b82f6; margin-bottom: 10px; }
                .receipt-info { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin: 30px 0; }
                .info-section h3 { color: #3b82f6; margin-bottom: 15px; }
                .info-item { margin: 8px 0; }
                .info-item strong { color: #1f2937; }
                .amount { text-align: center; margin: 40px 0; padding: 20px; background: #f8fafc; border-radius: 8px; }
                .amount-value { font-size: 36px; font-weight: bold; color: #059669; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 20px; }
                .status-approved { background: #dcfce7; color: #16a34a; padding: 4px 12px; border-radius: 20px; font-weight: 500; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo'>Juntify</div>
                <h2>" . __('profile.receipt.heading') . "</h2>
                <p>" . __('profile.receipt.receipt_number', ['number' => str_pad($payment->id, 8, '0', STR_PAD_LEFT)]) . "</p>
            </div>

            <div class='receipt-info'>
                <div class='info-section'>
                    <h3>" . __('profile.receipt.client_info') . "</h3>
                    <div class='info-item'><strong>" . __('profile.receipt.name') . ":</strong> {$user->name}</div>
                    <div class='info-item'><strong>" . __('profile.receipt.email') . ":</strong> {$user->email}</div>
                    <div class='info-item'><strong>" . __('profile.receipt.user') . ":</strong> {$user->username}</div>
                </div>

                <div class='info-section'>
                    <h3>" . __('profile.receipt.payment_info') . "</h3>
                    <div class='info-item'><strong>" . __('profile.receipt.date') . ":</strong> {$createdAt}</div>
                    <div class='info-item'><strong>" . __('profile.receipt.status') . ":</strong> <span class='status-approved'>" . __('profile.receipt.status_approved') . "</span></div>
                    <div class='info-item'><strong>" . __('profile.receipt.method') . ":</strong> " . ucfirst($payment->payment_method ?? 'MercadoPago') . "</div>
                    " . ($payment->external_id ? "<div class='info-item'><strong>" . __('profile.receipt.external_id') . ":</strong> {$payment->external_id}</div>" : "") . "
                </div>
            </div>

            <div class='amount'>
                <div class='info-item'><strong>" . __('profile.receipt.concept') . ":</strong> " . ($payment->description ?: ($payment->plan_name ?? 'Plan Enterprise')) . "</div>
                <div class='amount-value'>$" . number_format($payment->amount, 2) . " " . ($payment->currency ?? 'MXN') . "</div>
            </div>

            <div class='footer'>
                <p><strong>Juntify</strong> - " . __('profile.receipt.platform') . "</p>
                <p>" . __('profile.receipt.footer_notice') . "</p>
                <p>" . __('profile.receipt.generated_at', ['datetime' => now()->format('d/m/Y H:i:s')]) . "</p>
            </div>
        </body>
        </html>";
    }
}
