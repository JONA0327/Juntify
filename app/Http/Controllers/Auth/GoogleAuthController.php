<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;     // ← Importa el controlador base
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\Drive;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;

class GoogleAuthController extends Controller
{
    protected function createClient(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setScopes([Oauth2::USERINFO_EMAIL, Drive::DRIVE, Calendar::CALENDAR]);

        return $client;
    }

    public function redirect(Request $request)
    {
        // Track source to decide post-OAuth redirect
        $from = $request->query('from');
        if ($from === 'organization') {
            $orgId = $request->query('organization_id');
            session([
                'google_oauth_from' => 'organization',
                'google_oauth_org_id' => $orgId,
            ]);
        } else {
            // Clear any stale flag
            session()->forget('google_oauth_from');
            session()->forget('google_oauth_org_id');
        }
        // Optional explicit return URL (must be a safe relative path)
        $returnUrl = $request->query('return');
        if ($returnUrl && is_string($returnUrl)) {
            // Basic safety: allow only relative URLs within app
            if (str_starts_with($returnUrl, '/') && !preg_match('#^//|https?://#i', $returnUrl)) {
                session(['google_oauth_return' => $returnUrl]);
            }
        } else {
            session()->forget('google_oauth_return');
        }
        $client = $this->createClient();
        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $client = $this->createClient();
        // This method only stores the OAuth tokens. Folder creation is handled
        // from the profile page once the user is connected. (Note: we now auto-create
        // a default root + subfolders as part of the callback for personal accounts.)
        $token = $client->fetchAccessTokenWithAuthCode($request->input('code'));

        if (isset($token['error'])) {
            return redirect()->route('profile.show')
                ->withErrors(['drive' => 'No se pudo autorizar Google']);
        }

        if (!Auth::check()) {
            return redirect()->route('login')->withErrors([
                'auth' => 'Debes iniciar sesi\xC3\xB3n para conectar Google Drive',
            ]);
        }

        $client->setAccessToken($token);

        $user = Auth::user();

        $from = session()->pull('google_oauth_from'); // consume flag
        $orgId = session()->pull('google_oauth_org_id');
        $returnUrl = session()->pull('google_oauth_return');

    // Separar completamente los tokens: personal vs organizacional
        if ($from === 'organization' && $orgId) {
            // Guardar solo en organization_google_tokens para organizaciones
            OrganizationGoogleToken::updateOrCreate(
                ['organization_id' => $orgId],
                [
                    'access_token'  => $token['access_token'] ?? '',
                    'refresh_token' => $token['refresh_token'] ?? '',
                    'expiry_date'   => now()->addSeconds($token['expires_in'] ?? 3600),
                ]
            );
        } else {
            // Guardar solo en google_tokens para uso personal
            GoogleToken::updateOrCreate(
                ['username' => $user->username],
                [
                    'access_token'  => $token['access_token'] ?? '',
                    'refresh_token' => $token['refresh_token'] ?? '',
                    'expiry_date'   => now()->addSeconds($token['expires_in'] ?? 3600),
                ]
            );

            // Auto-setup: create default root + subfolders if not present, or share existing ones
            try {
                $googleToken = \App\Models\GoogleToken::where('username', $user->username)->first();
                $serviceEmail = config('services.google.service_account_email');
                
                // Si ya existe una carpeta raíz, asegurar que esté compartida con la cuenta de servicio
                if ($googleToken && !empty($googleToken->recordings_folder_id)) {
                    try {
                        $existingFolder = \App\Models\Folder::where('google_token_id', $googleToken->id)
                            ->where('google_id', $googleToken->recordings_folder_id)
                            ->whereNull('parent_id')
                            ->first();
                            
                        if ($existingFolder && $serviceEmail) {
                            /** @var \App\Services\GoogleServiceAccount $serviceAccount */
                            $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
                            /** @var \App\Services\GoogleDriveService $driveService */
                            $driveService = app(\App\Services\GoogleDriveService::class);
                            
                            // Configurar el driveService con el token del usuario
                            if ($googleToken->access_token) {
                                $driveService->setAccessToken([
                                    'access_token' => $googleToken->access_token,
                                    'refresh_token' => $googleToken->refresh_token,
                                    'expiry_date' => $googleToken->expiry_date,
                                ]);
                            }
                            
                            $shared = false;
                            
                            // Intentar compartir con OAuth del usuario (más probable que funcione)
                            try {
                                $driveService->shareFolder($existingFolder->google_id, $serviceEmail);
                                $shared = true;
                                Log::info('Carpeta raíz existente compartida con cuenta de servicio vía OAuth', [
                                    'folder_id' => $existingFolder->google_id,
                                    'user' => $user->username
                                ]);
                            } catch (\Throwable $e1) {
                                Log::debug('Falló compartir carpeta raíz existente vía OAuth', ['error' => $e1->getMessage()]);
                                
                                // Fallback: intentar con Service Account directo
                                try {
                                    $serviceAccount->shareFolder($existingFolder->google_id, $serviceEmail);
                                    $shared = true;
                                    Log::info('Carpeta raíz existente compartida con cuenta de servicio vía SA directo');
                                } catch (\Throwable $e2) {
                                    Log::debug('Falló compartir carpeta raíz existente vía SA directo', ['error' => $e2->getMessage()]);
                                    
                                    // Fallback: intentar con impersonación
                                    if ($user->email && !GoogleServiceAccount::impersonationDisabled()) {
                                        try {
                                            $serviceAccount->impersonate($user->email);
                                            $serviceAccount->shareFolder($existingFolder->google_id, $serviceEmail);
                                            $shared = true;
                                            Log::info('Carpeta raíz existente compartida vía impersonación');
                                        } catch (\Throwable $e3) {
                                            Log::warning('Falló compartir carpeta raíz existente (todos los métodos)', ['error' => $e3->getMessage()]);
                                        } finally {
                                            try { $serviceAccount->impersonate(null); } catch (\Throwable $e) { /* ignore */ }
                                        }
                                    }
                                }
                            }
                            
                            // Compartir subcarpetas existentes también
                            if ($shared) {
                                $subfolders = \App\Models\Subfolder::where('folder_id', $existingFolder->id)->get();
                                foreach ($subfolders as $subfolder) {
                                    try {
                                        $driveService->shareFolder($subfolder->google_id, $serviceEmail);
                                        Log::info('Subcarpeta existente compartida', ['name' => $subfolder->name]);
                                    } catch (\Throwable $e) {
                                        Log::debug('No se pudo compartir subcarpeta existente', [
                                            'name' => $subfolder->name,
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Error al intentar compartir carpeta raíz existente', ['error' => $e->getMessage()]);
                    }
                }
                
                if ($googleToken && empty($googleToken->recordings_folder_id)) {
                    // Create default root under configured parent (if any) and ensure subfolders
                    /** @var \App\Services\GoogleServiceAccount $serviceAccount */
                    $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
                    /** @var \App\Services\GoogleDriveService $driveService */
                    $driveService = app(\App\Services\GoogleDriveService::class);

                    // Ensure DriveService has a fresh token for reading/writing
                    if ($googleToken->access_token) {
                        $driveService->setAccessToken([
                            'access_token' => $googleToken->access_token,
                            'refresh_token' => $googleToken->refresh_token,
                            'expiry_date' => $googleToken->expiry_date,
                        ]);
                    }

                    $parentRootId = (string) (config('drive.root_folder_id') ?: '');
                    $defaultRootName = config('drive.default_root_folder_name', 'Juntify Recordings');

                    $folderId = null;
                    $impersonated = false;
                    $createdWithOAuth = false;
                    try {
                        if (!empty($parentRootId)) {
                            // Create under a configured parent using Service Account
                            $folderId = $serviceAccount->createFolder($defaultRootName, $parentRootId);
                        } else {
                            // Create in the user's My Drive via impersonation
                            $ownerEmail = $user->email;
                            if ($ownerEmail) {
                                $serviceAccount->impersonate($ownerEmail);
                                $impersonated = true;
                                $folderId = $serviceAccount->createFolder($defaultRootName);
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fallback: try OAuth client if SA creation fails
                        try { 
                            $folderId = $driveService->createFolder($defaultRootName, $parentRootId ?: null); 
                            $createdWithOAuth = true;
                        } catch (\Throwable $e2) { /* keep null */ }
                    } finally {
                        if ($impersonated) {
                            try { $serviceAccount->impersonate(null); } catch (\Throwable $e) { /* ignore */ }
                        }
                    }

                    // Siempre compartir la carpeta raíz con la cuenta de servicio
                    if ($folderId) {
                        $serviceEmail = config('services.google.service_account_email');
                        if ($serviceEmail) {
                            try {
                                if ($createdWithOAuth) {
                                    // Si se creó con OAuth, usar el driveService para compartir
                                    $driveService->shareFolder($folderId, $serviceEmail);
                                } else {
                                    // Si se creó con Service Account, usar serviceAccount para compartir
                                    $serviceAccount->shareFolder($folderId, $serviceEmail);
                                }
                                Log::info('Carpeta raíz compartida automáticamente con cuenta de servicio', [
                                    'folder_id' => $folderId,
                                    'service_email' => $serviceEmail
                                ]);
                            } catch (\Throwable $e) {
                                Log::warning('No se pudo compartir la carpeta raíz con la cuenta de servicio', [
                                    'folder_id' => $folderId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }

                    if ($folderId) {
                        // Persist root Folder and link to token
                        $folderModel = \App\Models\Folder::create([
                            'google_token_id' => $googleToken->id,
                            'google_id'       => $folderId,
                            'name'            => $defaultRootName,
                            'parent_id'       => null,
                        ]);
                        $googleToken->recordings_folder_id = $folderId;
                        $googleToken->save();

                        // Asegurar que el usuario tenga permisos sobre la carpeta raíz si la creó el Service Account sin impersonación
                        try {
                            if (!$impersonated && $user->email) {
                                // Compartir la carpeta raíz con el usuario para evitar errores de acceso posteriores en ProfileController
                                $serviceAccount->shareItem($folderId, $user->email, 'writer');
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo compartir la carpeta raíz con el usuario', [
                                'folder_id' => $folderId,
                                'user_email' => $user->email,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // Ensure standard subfolders from config (includes Audios, Transcripciones, etc.)
                        try {
                            /** @var \App\Http\Controllers\DriveController $driveController */
                            $driveController = app(\App\Http\Controllers\DriveController::class);
                            // Call private-like helper via reflection is not ideal; reuse logic inline:
                            $serviceEmail = config('services.google.service_account_email');
                            $needed = config('drive.default_subfolders', ['Audios', 'Transcripciones']);
                            foreach ($needed as $name) {
                                $subId = null;
                                // Estrategia en cascada: ServiceAccount directa -> SA impersonada -> OAuth
                                try { $subId = $serviceAccount->createFolder($name, $folderId); }
                                catch (\Throwable $e1) {
                                    Log::debug('Fallo SA directa creando subcarpeta callback', ['name' => $name, 'error' => $e1->getMessage()]);
                                    if ($user->email) {
                                        try { $serviceAccount->impersonate($user->email); $subId = $serviceAccount->createFolder($name, $folderId); }
                                        catch (\Throwable $e2) {
                                            Log::debug('Fallo SA impersonada creando subcarpeta callback, intentando OAuth', ['name' => $name, 'error' => $e2->getMessage()]);
                                        } finally { try { $serviceAccount->impersonate(null); } catch (\Throwable $eR) { /* ignore */ } }
                                    }
                                }
                                if (!$subId) {
                                    try { $subId = $driveService->createFolder($name, $folderId); }
                                    catch (\Throwable $e3) {
                                        Log::warning('Fallo total creando subcarpeta default en callback', ['name' => $name, 'error' => $e3->getMessage()]);
                                    }
                                }
                                if ($subId) {
                                    try {
                                        \App\Models\Subfolder::firstOrCreate([
                                            'folder_id' => $folderModel->id,
                                            'google_id' => $subId,
                                        ], ['name' => $name]);
                                        try { if ($serviceEmail) { $serviceAccount->shareFolder($subId, $serviceEmail); } } catch (\Throwable $eShareSa) { /* ignore */ }
                                        try { if ($user->email) { $serviceAccount->shareItem($subId, $user->email, 'writer'); } } catch (\Throwable $eShareUser) { /* ignore */ }
                                    } catch (\Throwable $persistE) {
                                        Log::warning('Fallo persistiendo subcarpeta default en callback', ['name' => $name, 'error' => $persistE->getMessage()]);
                                    }
                                }
                            }
                        } catch (\Throwable $e) { /* ignore subfolder ensure failures */ }
                    }
                }
            } catch (\Throwable $e) {
                // Swallow auto-setup errors to not block OAuth success
            }
        }

        if ($returnUrl) {
            return redirect($returnUrl)
                ->with('success', 'Google Drive conectado. Ya puedes gestionar carpetas de la organización.');
        }
        if ($from === 'organization') {
            return redirect()->route('organization.index')
                             ->with('success', 'Google Drive conectado. Ya puedes gestionar carpetas de la organización.');
        }

    return redirect()->route('profile.show')
             ->with('success', 'Google Drive conectado. Juntify ya estableció una carpeta automática para guardar tus reuniones. Si deseas establecer una carpeta raíz diferente, ingresa el número de la carpeta.');
    }

    public function disconnect()
    {
        $user  = Auth::user();
        $token = $user->googleToken;

        if ($token) {
            $client = $this->createClient();
            if ($token->access_token) {
                try {
                    $client->revokeToken($token->access_token);
                } catch (\Throwable $e) {
                    // Ignore revoke errors
                }
            }

            $token->update([
                'access_token'  => null,
                'refresh_token' => null,
                'expiry_date'          => null,
            ]);
        }

        return redirect()->back()->with('success', 'Google Drive desconectado');
    }

    public function disconnectOrganization(Request $request)
    {
        $organizationId = $request->input('organization_id');

        if (!$organizationId) {
            return redirect()->back()->withErrors(['error' => 'ID de organización requerido']);
        }

        $orgToken = OrganizationGoogleToken::where('organization_id', $organizationId)->first();

        if ($orgToken) {
            $client = $this->createClient();
            if ($orgToken->access_token) {
                try {
                    $client->revokeToken($orgToken->access_token);
                } catch (\Throwable $e) {
                    // Ignore revoke errors
                }
            }

            $orgToken->update([
                'access_token'  => null,
                'refresh_token' => null,
                'expiry_date'   => null,
            ]);
        }

        return redirect()->back()->with('success', 'Google Drive organizacional desconectado');
    }
}
