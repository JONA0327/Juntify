<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationDriveHelper;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

uses()->afterEach(function () {
    \Mockery::close();
});

test('user can create container', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/content-containers', [
        'name' => 'New Container',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'container' => [
                'name' => 'New Container',
            ],
        ]);

    $this->assertDatabaseHas('meeting_content_containers', [
        'username' => $user->username,
        'name' => 'New Container',
    ]);
});

test('personal containers are listed with null group name', function () {
    $user = User::factory()->create(['username' => 'eve']);

    $container = MeetingContentContainer::create([
        'username' => $user->username,
        'name' => 'Personal Notes',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/content-containers');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('containers.0.id', $container->id)
        ->assertJsonPath('containers.0.group_name', null);
});

test('user can add meeting to container and list meetings', function () {
    $user = User::factory()->create(['username' => 'bob']);

    $container = MeetingContentContainer::create([
        'username' => $user->username,
        'name' => 'Work',
        'is_active' => true,
    ]);

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Weekly',
    ]);

    $addResponse = $this->actingAs($user, 'sanctum')->postJson("/api/content-containers/{$container->id}/meetings", [
        'meeting_id' => $meeting->id,
    ]);

    $addResponse->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseHas('meeting_content_relations', [
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $listResponse = $this->actingAs($user, 'sanctum')->getJson("/api/content-containers/{$container->id}/meetings");

    $listResponse->assertOk()
        ->assertJson([
            'success' => true,
            'meetings' => [
                [
                    'id' => $meeting->id,
                    'meeting_name' => 'Weekly',
                ],
            ],
        ]);
});

test('user can remove meeting from container', function () {
    $user = User::factory()->create(['username' => 'carol']);

    $container = MeetingContentContainer::create([
        'username' => $user->username,
        'name' => 'Work',
        'is_active' => true,
    ]);

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Weekly',
    ]);

    MeetingContentRelation::create([
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/content-containers/{$container->id}/meetings/{$meeting->id}");

    $response->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseMissing('meeting_content_relations', [
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);
});

test('creating a group container stores drive folder information', function () {
    $user = User::factory()->create(['username' => 'diana']);
    $organization = Organization::create([
        'nombre_organizacion' => 'Acme',
        'descripcion' => 'Test org',
        'admin_id' => $user->id,
    ]);

    $group = Group::create([
        'id_organizacion' => $organization->id,
        'nombre_grupo' => 'Equipo A',
        'descripcion' => 'Grupo de prueba',
    ]);

    DB::table('group_user')->insert([
        'id_grupo' => $group->id,
        'user_id' => $user->id,
        'rol' => 'colaborador',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $helper = \Mockery::mock(OrganizationDriveHelper::class);
    $helper->shouldReceive('ensureContainerFolder')
        ->once()
        ->andReturn([
            'id' => 'drive-folder-123',
            'metadata' => ['parent_google_id' => 'group-folder-1'],
        ]);

    $this->instance(OrganizationDriveHelper::class, $helper);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/content-containers', [
        'name' => 'Documentos',
        'group_id' => $group->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('container.drive_folder_id', 'drive-folder-123');

    $this->assertDatabaseHas('meeting_content_containers', [
        'group_id' => $group->id,
        'drive_folder_id' => 'drive-folder-123',
    ]);

    $this->assertDatabaseHas('meeting_content_containers', [
        'id' => $response['container']['id'],
        'metadata->parent_google_id' => 'group-folder-1',
    ]);
});
