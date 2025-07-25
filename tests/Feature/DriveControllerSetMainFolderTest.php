<?php

use App\Models\User;
use App\Models\GoogleToken;

it('updates the existing token with the provided folder id', function () {
    $user = User::factory()->create(['username' => 'tester']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
        'recordings_folder_id' => 'old-folder',
    ]);

    $response = $this->actingAs($user)->post('/drive/set-main-folder', [
        'id' => 'new-folder',
    ]);

    $response->assertOk()->assertJson(['id' => 'new-folder']);

    $this->assertDatabaseHas('google_tokens', [
        'id' => $token->id,
        'recordings_folder_id' => 'new-folder',
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
