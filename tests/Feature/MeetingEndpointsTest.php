<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\SharedMeeting;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Container;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Storage;
use Mockery;

uses(RefreshDatabase::class);

test('shared meetings v2 endpoint returns meetings for authenticated user', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();

    $meeting = createLegacyMeeting($owner, [
        'meeting_name' => 'Shared Meeting',
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

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Meeting in Container',
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

    $meetingWithout = createLegacyMeeting($user, [
        'meeting_name' => 'Free Meeting',
    ]);

    $meetingWith = createLegacyMeeting($user, [
        'meeting_name' => 'Contained Meeting',
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

    $meetingWithout = createLegacyMeeting($user, [
        'meeting_name' => 'Free Meeting',
    ]);

    $meetingWith = createLegacyMeeting($user, [
        'meeting_name' => 'Contained Meeting',
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

    $legacy = createLegacyMeeting($user, [
        'meeting_name' => 'Legacy',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings');

    $response->assertOk()
        ->assertJsonFragment(['id' => $legacy->id, 'is_legacy' => true])
        ->assertJsonMissing(['is_legacy' => false]);
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

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Legacy Meeting',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings/' . $meeting->id);

    $response->assertOk()
        ->assertJsonFragment(['id' => $meeting->id, 'is_legacy' => true]);
});

test('show legacy meeting returns audio data', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'My Meeting',
        'audio_drive_id' => 'folder123',
        'audio_download_url' => '',
        'transcript_drive_id' => 'trans123',
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired(){ return false; } }; }
        public function downloadFileContent($fileId) { return json_encode(['summary' => 's', 'key_points' => [], 'transcription' => 't']); }
        public function findAudioInFolder($folderId, $meetingTitle, $meetingId) { return ['fileId' => 'file456', 'downloadUrl' => 'https://example.com/audio.ogg']; }
        public function getFileInfo($fileId) { return new class { public function getParents(){ return []; } public function getName(){ return 'Parent'; } }; }
    });

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/meetings/' . $meeting->id);

    $response->assertOk()
        ->assertJsonFragment([
            'audio_path' => 'https://example.com/audio.ogg',
            'audio_drive_id' => 'file456',
            'is_legacy' => true,
        ]);
});

test('shared meeting audio endpoint uses service account temporary file when available', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $recipient = User::factory()->create();

    GoogleToken::create([
        'username' => $recipient->username,
        'access_token' => 'test-access',
        'refresh_token' => 'test-refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = createLegacyMeeting($owner, [
        'meeting_name' => 'Service Account Meeting',
        'audio_drive_id' => 'sa-file-id',
        'audio_download_url' => '',
    ]);

    SharedMeeting::create([
        'meeting_id' => $meeting->id,
        'shared_by' => $owner->id,
        'shared_with' => $recipient->id,
        'status' => 'accepted',
        'shared_at' => now(),
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired() { return false; } }; }
        public function refreshToken($refreshToken) { return ['access_token' => 'new-token', 'expires_in' => 3600]; }
        public function findAudioInFolder($folderId, $meetingTitle, $meetingId) { return []; }
        public function downloadFileContent($fileId) { return ''; }
        public function getFileInfo($fileId) { return new class {
            public function getParents() { return []; }
            public function getName() { return 'Parent'; }
            public function getMimeType() { return 'audio/ogg'; }
        }; }
        public function getWebContentLink($fileId) { return null; }
    });

    app()->instance(\App\Services\GoogleServiceAccount::class, new class {
        public ?string $impersonated = null;

        public function impersonate($email)
        {
            $this->impersonated = $email;
        }

        public function downloadFile($fileId)
        {
            return 'fake audio';
        }

        public function getClient()
        {
            return new class {
                public function fetchAccessTokenWithAssertion()
                {
                    return ['access_token' => 'sa-token'];
                }
            };
        }
    });

    Storage::disk('public')->delete('temp/stream_' . $meeting->id . '.ogg');
    Storage::disk('public')->makeDirectory('temp');

    $response = $this->actingAs($recipient, 'sanctum')->get('/api/meetings/' . $meeting->id . '/audio');

    $expectedPath = storage_path('app/public/temp/stream_' . $meeting->id . '.ogg');
    expect(file_exists($expectedPath))->toBeTrue();

    $expectedUrl = asset('storage/temp/stream_' . $meeting->id . '.ogg');
    $response->assertRedirect($expectedUrl);
});

test('shared meeting audio endpoint falls back to impersonation after initial service account failure', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $recipient = User::factory()->create();

    GoogleToken::create([
        'username' => $recipient->username,
        'access_token' => 'test-access',
        'refresh_token' => 'test-refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = createLegacyMeeting($owner, [
        'meeting_name' => 'Service Account Meeting',
        'audio_drive_id' => 'sa-file-id',
        'audio_download_url' => '',
    ]);

    SharedMeeting::create([
        'meeting_id' => $meeting->id,
        'shared_by' => $owner->id,
        'shared_with' => $recipient->id,
        'status' => 'accepted',
        'shared_at' => now(),
    ]);

    app()->instance(GoogleDriveService::class, new class {
        public function setAccessToken($token) {}
        public function getClient() { return new class { public function isAccessTokenExpired() { return false; } }; }
        public function refreshToken($refreshToken) { return ['access_token' => 'new-token', 'expires_in' => 3600]; }
        public function findAudioInFolder($folderId, $meetingTitle, $meetingId) { return []; }
        public function downloadFileContent($fileId) { return ''; }
        public function getFileInfo($fileId) { return new class {
            public function getParents() { return []; }
            public function getName() { return 'Parent'; }
            public function getMimeType() { return 'audio/ogg'; }
        }; }
        public function getWebContentLink($fileId) { return null; }
    });

    $serviceAccount = new class {
        public ?string $impersonated = null;
        public int $downloadAttempts = 0;

        public function impersonate($email)
        {
            $this->impersonated = $email;
        }

        public function downloadFile($fileId)
        {
            $this->downloadAttempts++;

            if ($this->downloadAttempts === 1) {
                throw new \Exception('service account download failed');
            }

            if (!$this->impersonated) {
                throw new \Exception('impersonation was not triggered');
            }

            return 'fake audio';
        }

        public function getClient()
        {
            return new class {
                public function fetchAccessTokenWithAssertion()
                {
                    return ['access_token' => 'sa-token'];
                }
            };
        }

        public function getFileInfo($fileId)
        {
            return new class {
                public function getName() { return 'audio'; }
                public function getMimeType() { return 'audio/ogg'; }
            };
        }
    };

    app()->instance(\App\Services\GoogleServiceAccount::class, $serviceAccount);

    Storage::disk('public')->delete('temp/stream_' . $meeting->id . '.ogg');
    Storage::disk('public')->makeDirectory('temp');

    $response = $this->actingAs($recipient, 'sanctum')->get('/api/meetings/' . $meeting->id . '/audio');

    $expectedPath = storage_path('app/public/temp/stream_' . $meeting->id . '.ogg');
    expect(file_exists($expectedPath))->toBeTrue();

    $expectedUrl = asset('storage/temp/stream_' . $meeting->id . '.ogg');
    $response->assertRedirect($expectedUrl);

    expect($serviceAccount->downloadAttempts)->toBe(2);
    expect($serviceAccount->impersonated)->toBe('owner@example.com');
});


test('destroy meeting returns 500 when Google Drive deletion fails', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = createLegacyMeeting($user, [
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

    $meeting = createLegacyMeeting($user, [
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

