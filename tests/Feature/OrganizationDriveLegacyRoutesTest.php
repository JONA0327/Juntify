<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 for removed organization subfolder routes', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/organizations/{$organization->id}/drive/subfolders", ['name' => 'legacy'])
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson("/api/organizations/{$organization->id}/drive/subfolders")
        ->assertNotFound();

    $this->actingAs($user)
        ->patchJson("/api/organizations/{$organization->id}/drive/subfolders/123", ['name' => 'legacy'])
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson("/api/organizations/{$organization->id}/drive/subfolders/123")
        ->assertNotFound();
});
