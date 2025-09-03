<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;

uses(RefreshDatabase::class);

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

test('user can add meeting to container and list meetings', function () {
    $user = User::factory()->create(['username' => 'bob']);

    $container = MeetingContentContainer::create([
        'username' => $user->username,
        'name' => 'Work',
        'is_active' => true,
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Weekly',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
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

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Weekly',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
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
