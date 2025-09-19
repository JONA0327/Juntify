<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;

uses(RefreshDatabase::class);

test('meeting audio endpoint resolves folder ids before streaming', function () {
    $user = User::factory()->create(['username' => 'folder-user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'test-token',
        'refresh_token' => 'test-refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Folder Meeting',
        'audio_drive_id' => 'folder-123',
        'audio_download_url' => '',
    ]);

    $driveService = new class {
        public function setAccessToken($token) {}
        public function getClient()
        {
            return new class {
                public function isAccessTokenExpired()
                {
                    return false;
                }
                public function getAccessToken()
                {
                    return ['access_token' => 'token'];
                }
            };
        }
        public function findAudioInFolder($folderId, $meetingTitle, $meetingId)
        {
            return ['fileId' => 'resolved-file', 'downloadUrl' => null];
        }
        public function getFileInfo($fileId)
        {
            if ($fileId === 'folder-123') {
                return new class {
                    public function getMimeType()
                    {
                        return 'application/vnd.google-apps.folder';
                    }
                };
            }

            return new class {
                public function getMimeType()
                {
                    return 'audio/mpeg';
                }
                public function getName()
                {
                    return 'resolved.mp3';
                }
            };
        }
        public function downloadFileContent($fileId)
        {
            return 'binary audio data';
        }
    };

    app()->instance(GoogleDriveService::class, $driveService);

    Storage::disk('public')->delete('temp/Folder_Meeting_' . $meeting->id . '.mp3');
    Storage::disk('public')->makeDirectory('temp');

    $response = $this->actingAs($user, 'sanctum')->get('/api/meetings/' . $meeting->id . '/audio');

    $expectedUrl = asset('storage/temp/Folder_Meeting_' . $meeting->id . '.mp3');
    $response->assertRedirect($expectedUrl);

    $updatedMeeting = $meeting->fresh();
    expect($updatedMeeting->audio_drive_id)->toBe('resolved-file');
});
