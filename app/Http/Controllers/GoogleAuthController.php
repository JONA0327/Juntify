<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Oauth2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
        $client->setScopes([Oauth2::USERINFO_EMAIL, Drive::DRIVE]);

        return $client;
    }

    public function redirect()
    {
        $client = $this->createClient();
        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $client = $this->createClient();
        $token = $client->fetchAccessTokenWithAuthCode($request->input('code'));

        if (isset($token['error'])) {
            return redirect()->route('profile.show')
                ->withErrors(['drive' => 'No se pudo autorizar Google']);
        }

        $client->setAccessToken($token);
        $oauth2 = new Oauth2($client);
        $googleUser = $oauth2->userinfo->get();

        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'id' => (string) Str::uuid(),
                'username' => $googleUser->getId(),
                'full_name' => $googleUser->getName() ?: $googleUser->getEmail(),
                'password' => '',
                'roles' => 'free',
            ]
        );

        Auth::login($user);

        GoogleToken::updateOrCreate(
            ['username' => $user->username],
            [
                'access_token' => $token['access_token'] ?? '',
                'refresh_token' => $token['refresh_token'] ?? '',
                'expiry_date' => now()->addSeconds($token['expires_in'] ?? 3600),
            ]
        );

        return redirect()->route('profile.show')->with('success', 'Google Drive conectado');
    }
}
