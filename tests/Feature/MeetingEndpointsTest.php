<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\MeetingShare;
use App\Models\MeetingContainer;

uses(RefreshDatabase::class);

test('shared meetings endpoint returns meetings for authenticated user', function () {
    $owner = User::factory()->create(['username' => 'owner']);
    $recipient = User::factory()->create(['username' => 'recipient']);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $owner->username,
        'meeting_name' => 'Shared Meeting',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    MeetingShare::create([
        'meeting_id' => $meeting->id,
        'from_username' => $owner->username,
        'to_username' => $recipient->username,
    ]);

    $response = $this->actingAs($recipient)->getJson('/api/shared-meetings');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'meetings' => [
                [
                    'id' => $meeting->id,
                    'meeting_name' => 'Shared Meeting',
                ],
            ],
        ]);
});

test('containers endpoint returns containers with meeting count', function () {
    $user = User::factory()->create(['username' => 'user']);

    $container = MeetingContainer::create([
        'username' => $user->username,
        'name' => 'My Container',
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Meeting in Container',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    DB::table('container_meetings')->insert([
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/containers');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'containers' => [
                [
                    'id' => $container->id,
                    'name' => 'My Container',
                    'meetings_count' => 1,
                ],
            ],
        ]);
});

