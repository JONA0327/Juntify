<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\Container;

uses(RefreshDatabase::class);

test('user can create container', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $response = $this->actingAs($user)->postJson('/api/containers', [
        'name' => 'New Container',
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'container' => [
                'name' => 'New Container',
            ],
        ]);

    $this->assertDatabaseHas('containers', [
        'username' => $user->username,
        'name' => 'New Container',
    ]);
});

test('user can add meeting to container and list meetings', function () {
    $user = User::factory()->create(['username' => 'bob']);

    $container = Container::create([
        'username' => $user->username,
        'name' => 'Work',
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Weekly',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $addResponse = $this->actingAs($user)->postJson("/api/containers/{$container->id}/meetings", [
        'meeting_id' => $meeting->id,
    ]);

    $addResponse->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseHas('container_meetings', [
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $listResponse = $this->actingAs($user)->getJson("/api/containers/{$container->id}/meetings");

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
