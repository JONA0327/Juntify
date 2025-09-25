<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Services\GoogleDriveService;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Mockery;

it('updates the existing token with the provided folder id', function () {
    $user = User::factory()->create(['username' => 'tester']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
        'recordings_folder_id' => 'old-folder',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $file = Mockery::mock(DriveFile::class);
    $file->shouldReceive('getName')->andReturn('Folder Name');

    $filesResource = Mockery::mock();
    $filesResource->shouldReceive('get')
        ->with('new-folder', ['fields' => 'name'])
        ->andReturn($file);

    $drive = Mockery::mock(Drive::class);
    $drive->files = $filesResource;

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldReceive('getDrive')->andReturn($drive);

    $this->app->instance(GoogleDriveService::class, $service);

    $response = $this->actingAs($user)->post('/drive/set-main-folder', [
        'id' => 'new-folder',
    ]);

    $response->assertOk()->assertJson([
        'id'   => 'new-folder',
        'name' => 'Folder Name',
    ]);

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
    ]);

    expect($token->fresh()->recordings_folder_id)->toBe('new-folder');

    $this->assertDatabaseHas('folders', [
        'google_token_id' => $token->id,
        'google_id'       => 'new-folder',
        'name'            => 'Folder Name',
        'parent_id'       => null,
    ]);

    $this->assertDatabaseCount('google_tokens', 1);
});

it('does not create a token when none exists', function () {
    $user = User::factory()->create(['username' => 'tester']);

    $response = $this->actingAs($user)->post('/drive/set-main-folder', [
        'id' => 'new-folder',
    ]);

    $response->assertStatus(404);
    $this->assertDatabaseCount('google_tokens', 0);
});
