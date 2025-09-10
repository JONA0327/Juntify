<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\SharedMeeting;
use App\Models\TranscriptionLaravel;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Container;
use App\Models\Meeting;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use Mockery;

uses(RefreshDatabase::class);

test('shared meetings v2 endpoint returns meetings for authenticated user', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();

    $meeting = Meeting::factory()->create([
        'username' => $owner->username,
        'title' => 'Shared Meeting',
    ]);

    SharedMeeting::create([
        'meeting_id' => $meeting->id,
        'shared_by' => $owner->id,
        'shared_with' => $recipient->id,
        'status' => 'accepted',
        'shared_at' => now(),
    ]);

    $response = $this->actingAs($recipient, 'sanctum')->getJson('/api/shared-meetings/v2');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'meetings' => [
                [
                    'meeting_id' => $meeting->id,
                    'title' => 'Shared Meeting',
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

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/content-containers');

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

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings');

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

test('/api/meetings returns is_legacy flag', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'test-token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired(){ return false; } }; }
        public function searchFiles($name, $folderId) { return []; }
        public function getFileInfo($fileId) { return new class {
            public function getParents(){ return []; }
            public function getName(){ return 'Parent'; }
        }; }
        public function downloadFileContent($fileId) { return ''; }
    });

    $legacy = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Legacy',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $modern = Meeting::factory()->create([
        'username' => $user->username,
        'recordings_folder_id' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings');

    $response->assertOk()
        ->assertJsonFragment(['id' => $legacy->id, 'is_legacy' => true])
        ->assertJsonFragment(['id' => $modern->id, 'is_legacy' => false]);
});

test('show meeting returns is_legacy flag', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'test-token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired(){ return false; } }; }
        public function searchFiles($name, $folderId) { return []; }
        public function getFileInfo($fileId) { return new class {
            public function getParents(){ return []; }
            public function getName(){ return 'Parent'; }
        }; }
        public function downloadFileContent($fileId) { return ''; }
    });

    $meeting = Meeting::factory()->create([
        'username' => $user->username,
        'recordings_folder_id' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings/' . $meeting->id);

    $response->assertOk()
        ->assertJsonFragment(['id' => $meeting->id, 'is_legacy' => false]);
});

test('show legacy meeting returns audio data', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'My Meeting',
        'audio_drive_id' => 'folder123',
        'transcript_drive_id' => 'trans123',
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired(){ return false; } }; }
        public function downloadFileContent($fileId) { return json_encode(['summary' => 's', 'key_points' => [], 'transcription' => 't']); }
        public function findAudioInFolder($folderId, $meetingTitle, $meetingId) { return ['fileId' => 'file456', 'downloadUrl' => 'https://example.com/audio.mp3']; }
        public function getFileInfo($fileId) { return new class { public function getParents(){ return []; } public function getName(){ return 'Parent'; } }; }
    });

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings/' . $meeting->id);

    $response->assertOk()
        ->assertJsonFragment([
            'audio_path' => 'https://example.com/audio.mp3',
            'audio_drive_id' => 'file456',
            'is_legacy' => true,
        ]);
});


test('destroy meeting returns 500 when Google Drive deletion fails', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'transcript_drive_id' => 'drive-file',
    ]);

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('setAccessToken')->andReturnNull();
    $mock->shouldReceive('getClient')->andReturn(new class {
        public function isAccessTokenExpired() { return false; }
    });
    $mock->shouldReceive('deleteFile')->andThrow(new \Error('fail'));

    app()->instance(GoogleDriveService::class, $mock);

    $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/meetings/' . $meeting->id);

    $response->assertStatus(500);
    $this->assertDatabaseHas('transcriptions_laravel', ['id' => $meeting->id]);
});

test('destroy meeting removes record on successful Google Drive deletion', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'transcript_drive_id' => 'drive-file',
    ]);

    $mock = Mockery::mock(GoogleDriveService::class);
    $mock->shouldReceive('setAccessToken')->andReturnNull();
    $mock->shouldReceive('getClient')->andReturn(new class {
        public function isAccessTokenExpired() { return false; }
    });
    $mock->shouldReceive('deleteFile')->andReturnNull();

    app()->instance(GoogleDriveService::class, $mock);

    $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/meetings/' . $meeting->id);

    $response->assertOk();
    $this->assertDatabaseMissing('transcriptions_laravel', ['id' => $meeting->id]);
});

