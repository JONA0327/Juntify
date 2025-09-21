<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;     // ← Importa el controlador base
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\Drive;
use Google\Service\Calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

            // Auto-setup: create default root + subfolders if not present
            try {
                $googleToken = \App\Models\GoogleToken::where('username', $user->username)->first();
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
                    $defaultRootName = 'Grabaciones';

                    $folderId = null;
                    $impersonated = false;
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
                                // Give SA access for future automation
                                $serviceEmail = config('services.google.service_account_email');
                                if ($serviceEmail) {
                                    try { $serviceAccount->shareFolder($folderId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fallback: try OAuth client if SA creation fails
                        try { $folderId = $driveService->createFolder($defaultRootName, $parentRootId ?: null); } catch (\Throwable $e2) { /* keep null */ }
                    } finally {
                        if ($impersonated) {
                            try { $serviceAccount->impersonate(null); } catch (\Throwable $e) { /* ignore */ }
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

                        // Ensure standard subfolders (Audios, Transcripciones, Audios Pospuestos, Documentos)
                        try {
                            /** @var \App\Http\Controllers\DriveController $driveController */
                            $driveController = app(\App\Http\Controllers\DriveController::class);
                            // Call private-like helper via reflection is not ideal; reuse logic inline:
                            $serviceEmail = config('services.google.service_account_email');
                            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos'];
                            foreach ($needed as $name) {
                                try {
                                    $subId = $serviceAccount->createFolder($name, $folderId);
                                    \App\Models\Subfolder::firstOrCreate([
                                        'folder_id' => $folderModel->id,
                                        'google_id' => $subId,
                                    ], ['name' => $name]);
                                    try { if ($serviceEmail) { $serviceAccount->shareFolder($subId, $serviceEmail); } } catch (\Throwable $e) { /* ignore */ }
                                    // Share with user for OAuth access
                                    try { if ($user->email) { $serviceAccount->shareItem($subId, $user->email, 'writer'); } } catch (\Throwable $e) { /* ignore */ }
                                } catch (\Throwable $e) { /* ignore individual failures */ }
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
