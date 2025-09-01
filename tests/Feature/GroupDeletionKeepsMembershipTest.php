<?php

use App\Models\Organization;
use App\Models\Group;
use App\Models\User;

it('deleting a group does not remove users from organization', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $organization->users()->attach($user->id, ['rol' => 'invitado']);
    $organization->refreshMemberCount();

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Group',
        'descripcion' => 'desc',
        'miembros' => 0,
    ]);

    $group->users()->attach($admin->id, ['rol' => 'administrador']);
    $group->users()->attach($user->id, ['rol' => 'invitado']);

    $this->actingAs($admin);
    $this->deleteJson(route('api.groups.destroy', ['group' => $group->id]))->assertOk();

    expect($organization->fresh()->users()->where('users.id', $user->id)->exists())->toBeTrue();
});
