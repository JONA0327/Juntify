<?php

use App\Models\User;

// Ensure the sync subfolders route requires authentication
it('requires authentication to sync subfolders', function () {
    $response = $this->get('/drive/sync-subfolders');

    $response->assertRedirect('/login');
});

// Authenticated users should reach the controller (will fail with 404 when no token exists)
it('authenticated users get 200 with null root when no token exists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/drive/sync-subfolders');

    $response->assertStatus(200);
    $response->assertJson([
        'root_folder' => null,
    ]);
});
