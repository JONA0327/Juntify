<?php

use App\Models\Organization;
use App\Models\Group;
use App\Models\User;

it('refreshMemberCount counts unique users across groups and updates num_miembros', function () {
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
    ]);

    $group1 = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Group 1',
        'descripcion' => 'G1',
        'miembros' => 0,
    ]);

    $group2 = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Group 2',
        'descripcion' => 'G2',
        'miembros' => 0,
    ]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $group1->users()->attach($user1->id, ['rol' => 'invitado']);
    $group1->users()->attach($user2->id, ['rol' => 'invitado']);
    $group2->users()->attach($user1->id, ['rol' => 'invitado']);

    $organization->refreshMemberCount();

    expect($organization->fresh()->num_miembros)->toBe(2);
});
