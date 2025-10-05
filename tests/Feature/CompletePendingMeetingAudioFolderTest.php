<?php

use App\Models\Folder;
use App\Models\GoogleToken;
use App\Models\PendingRecording;
use App\Models\Subfolder;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
    Mockery::close();
});

it('moves pending meeting audio into the standard Audios subfolder by default', function () {
    config(['services.google.service_account_email' => 'service@example.com']);

    $user = User::factory()->create([
        'username' => 'pending-user',
        'email' => 'pending@example.com',
    ]);

    $token = GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'token-value',
        'refresh_token' => 'refresh-token',
        'expiry_date' => now()->addDay(),
    ]);

    $folder = Folder::create([
        'google_token_id' => $token->id,
        'google_id' => 'root-folder-id',
        'name' => 'Root Folder',
        'parent_id' => null,
    ]);

    Subfolder::create([
        'folder_id' => $folder->id,
        'google_id' => 'audio-subfolder-id',
        'name' => 'Audios',
    ]);

    $pending = PendingRecording::create([
        'username' => $user->username,
        'meeting_name' => 'Original Meeting',
        'audio_drive_id' => 'old-audio-file',
        'status' => 'processing',
    ]);

    $clientMock = Mockery::mock();
    $clientMock->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $capturedOptions = null;

    $filesMock = Mockery::mock();
    $filesMock->shouldReceive('get')
        ->once()
        ->with('old-audio-file', Mockery::on(function ($options) {
            return isset($options['supportsAllDrives']) && $options['supportsAllDrives'] === true;
        }))
        ->andReturn(new class {
            public function getParents()
            {
                return ['previous-parent'];
            }
        });

    $filesMock->shouldReceive('update')
        ->once()
        ->andReturnUsing(function ($fileId, $driveFile, $options) use (&$capturedOptions) {
            $capturedOptions = $options;
            return new class {
                public function getId()
                {
                    return 'new-audio-file';
                }
            };
        });

    $driveMock = new class($filesMock) {
        public $files;

        public function __construct($files)
        {
            $this->files = $files;
        }
    };

    $driveServiceMock = Mockery::mock(GoogleDriveService::class);
    $driveServiceMock->shouldReceive('setAccessToken')->once();
    $driveServiceMock->shouldReceive('getClient')->andReturn($clientMock);
    $driveServiceMock->shouldReceive('getDrive')->andReturn($driveMock);
    $driveServiceMock->shouldReceive('uploadFile')->andReturn('transcript-file-id');
    $driveServiceMock->shouldReceive('getFileLink')->with('new-audio-file')->andReturn('audio-url');
    $driveServiceMock->shouldReceive('getFileLink')->with('transcript-file-id')->andReturn('transcript-url');

    app()->instance(GoogleDriveService::class, $driveServiceMock);

    $serviceAccountMock = Mockery::mock(GoogleServiceAccount::class);
    app()->instance(GoogleServiceAccount::class, $serviceAccountMock);

    $planServiceMock = Mockery::mock(PlanLimitService::class);
    $planServiceMock->shouldReceive('canCreateAnotherMeeting')->andReturnTrue();
    $planServiceMock->shouldReceive('getLimitsForUser')->andReturn([
        'remaining' => null,
        'used_this_month' => 0,
        'max_meetings_per_month' => null,
    ]);
    app()->instance(PlanLimitService::class, $planServiceMock);

    $sessionData = [
        'pending_analysis_' . $pending->id => [
            'original_name' => 'OriginalMeeting.m4a',
            'temp_file' => storage_path('app/temp/pending-test.tmp'),
            'drive_file_id' => 'old-audio-file',
            'pending_id' => $pending->id,
            'username' => $user->username,
        ],
    ];

    $payload = [
        'pending_id' => $pending->id,
        'meeting_name' => 'Updated Meeting',
        'root_folder' => 'root-folder-id',
        'transcription_data' => [
            ['speaker' => 'S1', 'text' => 'Hola'],
        ],
        'analysis_results' => [
            'summary' => 'Resumen',
            'keyPoints' => [],
            'tasks' => [],
        ],
    ];

    $response = $this->actingAs($user, 'sanctum')
        ->withSession($sessionData)
        ->postJson('/api/pending-meetings/complete', $payload);

    $response->assertOk()->assertJson(['success' => true]);

    expect($capturedOptions['addParents'] ?? null)->toBe('audio-subfolder-id');
});
