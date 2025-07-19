<?php

use App\Models\User;

// Ensure the sync subfolders route requires authentication
it('requires authentication to sync subfolders', function () {
    $response = $this->get('/drive/sync-subfolders');

    $response->assertRedirect('/login');
});

// Authenticated users should reach the controller (will fail with 404 when no token exists)
it('authenticated users can access the sync route', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/drive/sync-subfolders');

    $response->assertStatus(404);
});
