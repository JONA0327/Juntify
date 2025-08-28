<?php

use App\Models\{Group, Organization, Notification, User};

it('returns updated member count when accepting a group invitation', function () {
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Group 1',
        'descripcion' => 'G1',
        'miembros' => 0,
    ]);

    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $notification = Notification::create([
        'remitente' => $sender->id,
        'emisor' => $receiver->id,
        'status' => 'pending',
        'message' => 'invitation',
        'type' => 'group_invitation',
        'data' => json_encode([
            'group_id' => $group->id,
            'role' => 'invitado',
        ]),
    ]);

    $response = $this->actingAs($receiver)->postJson(
        route('api.users.notifications.respond', $notification),
        ['action' => 'accept']
    );

    $response->assertOk()
        ->assertJson([
            'message' => 'Invitación aceptada',
            'num_miembros' => 1,
        ]);

    expect($group->fresh()->miembros)->toBe(1);
    expect($organization->fresh()->num_miembros)->toBe(1);
});

