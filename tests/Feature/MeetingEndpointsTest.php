<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\MeetingShare;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Container;

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

    $container = MeetingContentContainer::create([
        'username' => $user->username,
        'name' => 'My Container',
        'is_active' => true,
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Meeting in Container',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    MeetingContentRelation::create([
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/content-containers');

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

test('index view only lists meetings without containers', function () {
    $user = User::factory()->create(['username' => 'user']);

    $meetingWithout = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Free Meeting',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $meetingWith = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Contained Meeting',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $container = Container::create([
        'username' => $user->username,
        'name' => 'Container',
    ]);
    $container->meetings()->attach($meetingWith->id);

    $response = $this->actingAs($user)->get('/reuniones');

    $response->assertOk();
    $response->assertSee('Free Meeting');
    $response->assertDontSee('Contained Meeting');
});

test('/api/meetings excludes meetings inside containers', function () {
    $user = User::factory()->create(['username' => 'user']);

    $meetingWithout = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Free Meeting',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $meetingWith = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Contained Meeting',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $container = Container::create([
        'username' => $user->username,
        'name' => 'Container',
    ]);
    $container->meetings()->attach($meetingWith->id);

    $response = $this->actingAs($user)->getJson('/api/meetings');

    $response->assertOk()
        ->assertJsonCount(1, 'meetings')
        ->assertJsonFragment([
            'id' => $meetingWithout->id,
            'meeting_name' => 'Free Meeting',
        ])
        ->assertJsonMissing([
            'id' => $meetingWith->id,
            'meeting_name' => 'Contained Meeting',
        ]);
});

