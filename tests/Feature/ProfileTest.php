<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Services\GoogleDriveService;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Mockery;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('profile page shows when google token exists and folder is stored', function () {
    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'            => $user->username,
        'access_token'        => 'token',
        'refresh_token'       => 'refresh',
        'expiry_date'         => now()->addHour(),
        'recordings_folder_id'=> 'folder123',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $file = Mockery::mock(DriveFile::class);
    $file->shouldReceive('getName')->andReturn('My Recordings');

    $filesResource = Mockery::mock();
    $filesResource->shouldReceive('get')
        ->with('folder123', ['fields' => 'name'])
        ->andReturn($file);

    $drive = Mockery::mock(Drive::class);
    $drive->files = $filesResource;

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldReceive('getDrive')->andReturn($drive);

    $this->app->instance(GoogleDriveService::class, $service);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();

    $this->assertDatabaseHas('folders', [
        'google_token_id' => $token->id,
        'google_id'       => 'folder123',
        'name'            => 'My Recordings',
        'parent_id'       => null,
    ]);
});

test('profile page renders when google token is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $response->assertSee('Conectar Drive y Calendar');
});
