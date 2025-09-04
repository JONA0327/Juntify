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
            session(['google_oauth_from' => 'organization']);
        } else {
            // Clear any stale flag
            session()->forget('google_oauth_from');
        }
        $client = $this->createClient();
        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $client = $this->createClient();
        // This method only stores the OAuth tokens. Folder creation is handled
        // from the profile page once the user is connected.
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

        GoogleToken::updateOrCreate(
            ['username' => $user->username],
            [
                'access_token'  => $token['access_token'] ?? '',
                'refresh_token' => $token['refresh_token'] ?? '',
                'expiry_date'   => now()->addSeconds($token['expires_in'] ?? 3600),
            ]
        );

        $from = session()->pull('google_oauth_from'); // consume flag

        if ($from === 'organization') {
            return redirect()->route('organization.index')
                             ->with('success', 'Google Drive conectado. Ya puedes gestionar carpetas de la organización.');
        }

        return redirect()->route('profile.show')
                         ->with('success', 'Google Drive conectado. Crea tu carpeta principal desde tu perfil.');
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
}
