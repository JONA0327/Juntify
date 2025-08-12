<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Http\Controllers\Auth\GoogleAuthController;
use Google\Client;
use Mockery;

it('reconnects without losing folders', function () {
    $user = User::factory()->create(['username' => 'tester']);

    $token = GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'old-token',
        'refresh_token' => 'old-refresh',
        'expiry_date' => now()->addHour(),
        'recordings_folder_id' => 'folder123',
    ]);

    $folder = Folder::create([
        'google_token_id' => $token->id,
        'google_id' => 'folder123',
        'name' => 'Main',
        'parent_id' => null,
    ]);

    $sub = Subfolder::create([
        'folder_id' => $folder->id,
        'google_id' => 'sub123',
        'name' => 'Sub',
    ]);

    $response = $this->actingAs($user)->from('/profile')->post('/drive/disconnect');
    $response->assertRedirect('/profile');

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'recordings_folder_id' => 'folder123',
        'access_token' => null,
        'refresh_token' => null,
        'expiry_date' => null,
    ]);
    $this->assertDatabaseCount('google_tokens', 1);
    $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    $this->assertDatabaseHas('subfolders', ['id' => $sub->id]);

    $profile = $this->actingAs($user)->get('/profile');
    $profile->assertViewHas('driveConnected', false);
    $profile->assertViewHas('calendarConnected', false);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('fetchAccessTokenWithAuthCode')->with('code123')->andReturn([
        'access_token' => 'new-token',
        'refresh_token' => 'new-refresh',
        'expires_in' => 3600,
    ]);
    $client->shouldReceive('setAccessToken');

    $controller = Mockery::mock(GoogleAuthController::class)->makePartial();
    $controller->shouldReceive('createClient')->andReturn($client);
    app()->instance(GoogleAuthController::class, $controller);

    $resp = $this->actingAs($user)->get('/auth/google/callback?code=code123');
    $resp->assertRedirect('/profile');

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'access_token' => 'new-token',
        'refresh_token' => 'new-refresh',
    ]);
    $this->assertDatabaseCount('google_tokens', 1);
    $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    $this->assertDatabaseHas('subfolders', ['id' => $sub->id]);
    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'recordings_folder_id' => 'folder123',
    ]);
});
