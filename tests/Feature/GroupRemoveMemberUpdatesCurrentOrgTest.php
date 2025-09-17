<?php

use App\Models\Group;
use App\Models\Organization;
use App\Models\User;

it('sets current organization id to null when removing last membership', function () {
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
        'miembros' => 2,
    ]);

    $group->users()->attach($admin->id, ['rol' => 'administrador']);
    $group->users()->attach($user->id, ['rol' => 'invitado']);

    $user->update(['current_organization_id' => $organization->id]);

    $this->actingAs($admin);

    $this->deleteJson(route('api.groups.members.destroy', [
        'group' => $group->id,
        'user' => $user->id,
    ]))->assertOk();

    expect($user->fresh()->current_organization_id)->toBeNull();
    expect($organization->fresh()->users()->where('users.id', $user->id)->exists())->toBeFalse();
});
