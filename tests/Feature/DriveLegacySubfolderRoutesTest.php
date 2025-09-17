<?php

use App\Models\User;

it('returns 404 for legacy personal subfolder routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/drive/subfolder', ['name' => 'Legacy'])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete('/drive/subfolder/legacy-id')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/drive/sync-subfolders')
        ->assertNotFound();
});
