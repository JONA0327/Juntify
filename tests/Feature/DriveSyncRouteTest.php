<?php

use App\Models\User;

it('returns 404 for removed sync route when unauthenticated', function () {
    $this->get('/drive/sync-subfolders')->assertNotFound();
});

it('returns 404 for removed sync route when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/drive/sync-subfolders')
        ->assertNotFound();
});
