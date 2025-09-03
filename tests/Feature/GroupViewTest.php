<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Models\MeetingContentContainer;

uses(RefreshDatabase::class);

it('shows group data and containers for invited user', function () {
    $user = User::factory()->create(['roles' => 'free']);
    $owner = User::factory()->create();

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $owner->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Group',
        'descripcion' => 'desc',
        'miembros' => 0,
    ]);

    $group->users()->attach($user->id, ['rol' => 'invitado']);

    $container = MeetingContentContainer::create([
        'name' => 'Container',
        'description' => 'desc',
        'username' => $user->username,
        'group_id' => $group->id,
        'is_active' => true,
    ]);

    $this->actingAs($user, 'sanctum');

    $groupResponse = $this->getJson("/api/groups/{$group->id}");
    $groupResponse->assertOk()
        ->assertJsonFragment([
            'id' => $group->id,
            'nombre_grupo' => 'Group',
        ])
        ->assertJsonPath('containers.0.id', $container->id);

    $containersResponse = $this->getJson("/api/groups/{$group->id}/containers");
    $containersResponse->assertOk()
        ->assertJsonFragment([
            'id' => $container->id,
            'name' => 'Container',
        ]);
});

