<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use App\Http\Controllers\Auth\GoogleAuthController;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Mockery;
use RuntimeException;

// Test creation of main folder only
it('creates main folder', function () {
    Config::set('drive.root_folder_id', 'root123');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldNotReceive('createFolder');
    $service->shouldReceive('shareFolder')->never();

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('MainFolder', 'root123')
        ->once()
        ->andReturn('folder123');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios', 'folder123')
        ->andReturn('audios');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Transcripciones', 'folder123')
        ->andReturn('transcripciones');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios Pospuestos', 'folder123')
        ->andReturn('pospuestos');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Documentos', 'folder123')
        ->andReturn('documentos');
    $serviceAccount->shouldReceive('shareFolder')->andReturnNull();

    app()->instance(GoogleDriveService::class, $service);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($user)->post('/drive/main-folder', [
        'name' => 'MainFolder',
    ]);

    $response->assertOk()->assertJsonStructure(['id']);

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
    $this->assertDatabaseCount('folders', 1);
});

it('shares main folder with the service account email', function () {
    Config::set('drive.root_folder_id', 'root123');
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'testuser']);

    GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldNotReceive('createFolder');
    $service->shouldReceive('shareFolder')
        ->once()->with('folder123', 'svc@example.com');

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('MainFolder', 'root123')
        ->once()
        ->andReturn('folder123');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios', 'folder123')
        ->andReturn('audios');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Transcripciones', 'folder123')
        ->andReturn('transcripciones');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios Pospuestos', 'folder123')
        ->andReturn('pospuestos');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Documentos', 'folder123')
        ->andReturn('documentos');
    $serviceAccount->shouldReceive('shareFolder')->andReturnNull();

    app()->instance(GoogleDriveService::class, $service);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($user)->post('/drive/main-folder', [
        'name' => 'MainFolder',
    ]);

    $response->assertOk();
});

it('returns 400 when drive service throws a runtime exception', function () {
    Config::set('drive.root_folder_id', 'root123');

    $user = User::factory()->create(['username' => 'testuser']);

    GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldNotReceive('createFolder');
    $service->shouldReceive('shareFolder')->never();

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('MainFolder', 'root123')
        ->andThrow(new RuntimeException('Service account JSON path is invalid'));

    app()->instance(GoogleDriveService::class, $service);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($user)->post('/drive/main-folder', [
        'name' => 'MainFolder',
    ]);

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Service account JSON path is invalid']);
});

it('falls back to oauth client when service account cannot create main folder', function () {
    Config::set('drive.root_folder_id', 'root123');
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create([
        'username' => 'testuser',
        'email' => 'test@example.com',
    ]);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldReceive('createFolder')
        ->once()->with('MainFolder', 'root123')->andReturn('folder123');
    $service->shouldReceive('shareFolder')
        ->once()->with('folder123', 'svc@example.com');

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('MainFolder', 'root123')
        ->twice()
        ->andThrow(new RuntimeException('insufficient permissions'));
    $serviceAccount->shouldReceive('impersonate')->once()->with('test@example.com');
    $serviceAccount->shouldReceive('impersonate')->once()->with(null);
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios', 'folder123')
        ->andReturn('audios');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Transcripciones', 'folder123')
        ->andReturn('transcripciones');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios Pospuestos', 'folder123')
        ->andReturn('pospuestos');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Documentos', 'folder123')
        ->andReturn('documentos');
    $serviceAccount->shouldReceive('shareFolder')->andReturnNull();

    app()->instance(GoogleDriveService::class, $service);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($user)->post('/drive/main-folder', [
        'name' => 'MainFolder',
    ]);

    $response->assertOk()->assertJson(['id' => 'folder123']);

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'recordings_folder_id' => 'folder123',
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

it('requires a name to create main folder', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->post('/drive/main-folder', []);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect('/profile');
});

it('validates name is a string when creating main folder', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->post('/drive/main-folder', ['name' => ['array']]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect('/profile');
});

