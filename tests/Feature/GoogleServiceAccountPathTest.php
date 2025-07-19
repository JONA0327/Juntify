<?php

use App\Services\GoogleServiceAccount;
use Google\Client;
use Illuminate\Support\Facades\Config;

it('accepts relative service account json path when file exists', function () {
    $path = storage_path('app/credentials.json');
    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'dummy',
        'private_key_id' => 'key',
        'private_key' => "-----BEGIN PRIVATE KEY-----\n-----END PRIVATE KEY-----\n",
        'client_email' => 'dummy@example.com',
        'client_id' => 'client',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));

    Config::set('services.google.service_account_json', 'storage/app/credentials.json');

    $service = new GoogleServiceAccount();

    expect($service->getClient())->toBeInstanceOf(Client::class);

    unlink($path);
});
