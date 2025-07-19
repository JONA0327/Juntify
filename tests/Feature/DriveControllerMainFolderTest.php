<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Services\GoogleServiceAccount;
use App\Http\Controllers\Auth\GoogleAuthController;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Mockery;

// Test creation of main folder and default subfolders
it('creates main folder and subfolders', function () {
    Config::set('drive.root_folder_id', 'root123');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('impersonate')->once()->with($user->email);
    $service->shouldReceive('createFolder')
        ->once()->with('MainFolder', 'root123')->andReturn('folder123');
    $service->shouldReceive('createFolder')
        ->once()->with('Audios', 'folder123')->andReturn('audios123');
    $service->shouldReceive('createFolder')
        ->once()->with('Transcripciones', 'folder123')->andReturn('trans123');
    $service->shouldReceive('createFolder')
        ->once()->with('Resúmenes', 'folder123')->andReturn('summary123');

    app()->instance(GoogleServiceAccount::class, $service);

    $response = $this->actingAs($user)->post('/drive/main-folder', [
        'name' => 'MainFolder',
    ]);

    $response->assertOk()->assertJson([
        'id' => 'folder123',
        'subfolders' => [
            ['name' => 'Audios', 'id' => 'audios123'],
            ['name' => 'Transcripciones', 'id' => 'trans123'],
            ['name' => 'Resúmenes', 'id' => 'summary123'],
        ],
    ]);

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'recordings_folder_id' => 'folder123',
    ]);

    $this->assertDatabaseHas('folders', [
        'google_token_id' => $token->id,
        'google_id' => 'folder123',
        'name' => 'MainFolder',
        'parent_id' => null,
    ]);

    $this->assertDatabaseHas('subfolders', [
        'name' => 'Audios',
        'google_id' => 'audios123',
    ]);
    $this->assertDatabaseHas('subfolders', [
        'name' => 'Transcripciones',
        'google_id' => 'trans123',
    ]);
    $this->assertDatabaseHas('subfolders', [
        'name' => 'Resúmenes',
        'google_id' => 'summary123',
    ]);
});

// Test Google OAuth callback does not create folders
it('does not create folders on oauth callback', function () {
    $user = User::factory()->create(['username' => 'testuser']);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('fetchAccessTokenWithAuthCode')
        ->with('code123')
        ->andReturn([
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_in' => 3600,
        ]);
    $client->shouldReceive('setAccessToken');

    $controller = Mockery::mock(GoogleAuthController::class)->makePartial();
    $controller->shouldReceive('createClient')->andReturn($client);

    app()->instance(GoogleAuthController::class, $controller);

    $response = $this->actingAs($user)->get('/auth/google/callback?code=code123');

    $response->assertRedirect('/profile');

    $this->assertDatabaseHas('google_tokens', [
        'username' => $user->username,
        'access_token' => 'token',
    ]);

    $this->assertDatabaseCount('folders', 0);
});

