<?php

use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Organization;
use App\Models\User;

it('returns meetings from accessible containers', function () {
    $creator = User::factory()->create(['username' => 'creator-user']);
    $viewer = User::factory()->create(['username' => 'viewer-user']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Acme Inc.',
        'descripcion' => 'Org description',
        'admin_id' => $creator->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Product Team',
        'descripcion' => 'Team group',
        'miembros' => 0,
    ]);

    $group->users()->attach($viewer->id, ['rol' => Group::ROLE_COLABORADOR]);

    $container = MeetingContentContainer::create([
        'name' => 'Team Container',
        'description' => 'Container for the team',
        'username' => $creator->username,
        'group_id' => $group->id,
        'is_active' => true,
    ]);

    $meeting = createLegacyMeeting($creator, [
        'meeting_name' => 'Roadmap Review',
    ]);

    MeetingContentRelation::create([
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/ai-assistant/meetings');

    $response->assertOk()->assertJson(['success' => true]);

    $meetings = collect($response->json('meetings'));
    expect($meetings)->toHaveCount(1);

    $meetingData = $meetings->firstWhere('id', $meeting->id);
    expect($meetingData)->not->toBeNull();
    expect($meetingData['source'])->toBe('container');
    expect($meetingData['is_shared'])->toBeTrue();
    expect($meetingData['shared_by'])->toBe($container->name);
});
