<?php

use App\Http\Controllers\DriveController;
use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\PendingRecording;
use App\Services\GoogleServiceAccount;
use Illuminate\Support\Facades\Config;
use Mockery;

it('creates and shares standard folders when saving results', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'root123',
        'name'            => 'Root',
        'parent_id'       => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')
        ->once()->with('root123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Transcripciones', 'root123')->ordered()->andReturn('trans123');
    $service->shouldReceive('shareFolder')
        ->once()->with('trans123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Audio', 'root123')->ordered()->andReturn('audio123');
    $service->shouldReceive('shareFolder')
        ->once()->with('audio123', 'svc@example.com')->ordered();
    $service->shouldReceive('uploadFile')
        ->twice()->ordered()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')
        ->twice()->ordered()->andReturn('alink', 'tlink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'root123',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioData' => base64_encode('audio'),
        'audioMimeType' => 'audio/webm',
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk()
        ->assertJsonPath('drive_paths.transcriptions', 'Root/Transcripciones')
        ->assertJsonPath('drive_paths.audio', 'Root/Audio');

    $this->assertDatabaseHas('subfolders', [
        'folder_id' => Folder::first()->id,
        'name' => 'Transcripciones',
    ]);
    $this->assertDatabaseHas('subfolders', [
        'folder_id' => Folder::first()->id,
        'name' => 'Audio',
    ]);
});

it('derives the audio extension from the mime type map', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'root123',
        'name'            => 'Root',
        'parent_id'       => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')
        ->once()->with('root123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Transcripciones', 'root123')->ordered()->andReturn('trans123');
    $service->shouldReceive('shareFolder')
        ->once()->with('trans123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Audio', 'root123')->ordered()->andReturn('audio123');
    $service->shouldReceive('shareFolder')
        ->once()->with('audio123', 'svc@example.com')->ordered();
    $service->shouldReceive('uploadFile')
        ->once()->with('Meeting.ju', 'application/json', 'trans123', Mockery::type('string'))->ordered()->andReturn('t1');
    $service->shouldReceive('uploadFile')
        ->once()->with('Meeting.mp3', 'audio/mpeg', 'audio123', Mockery::type('string'))->ordered()->andReturn('a1');
    $service->shouldReceive('getFileLink')
        ->twice()->ordered()->andReturn('alink', 'tlink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'root123',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioData' => base64_encode('audio'),
        'audioMimeType' => 'audio/mpeg',
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();
});

it('completes save using a pending recording reference', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'testuser']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'root123',
        'name'            => 'Root',
        'parent_id'       => null,
    ]);

    $pending = PendingRecording::create([
        'username'           => $user->username,
        'meeting_name'       => 'OldName.mp3',
        'audio_drive_id'     => 'file123',
        'audio_download_url' => 'old-link',
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')
        ->once()->with('root123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Transcripciones', 'root123')->ordered()->andReturn('trans123');
    $service->shouldReceive('shareFolder')
        ->once()->with('trans123', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Audio', 'root123')->ordered()->andReturn('audio123');
    $service->shouldReceive('shareFolder')
        ->once()->with('audio123', 'svc@example.com')->ordered();
    $service->shouldReceive('uploadFile')
        ->once()->with('Meeting.ju', 'application/json', 'trans123', Mockery::type('string'))->ordered()->andReturn('t1');
    $service->shouldReceive('moveAndRenameFile')
        ->once()->with('file123', 'audio123', 'Meeting.mp3')->ordered()->andReturn('aud456');
    $service->shouldReceive('getFileLink')
        ->twice()->ordered()->andReturn('alink', 'tlink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'root123',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'pendingRecordingId' => $pending->id,
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk()
        ->assertJsonPath('audio_drive_id', 'aud456')
        ->assertJsonPath('audio_download_url', 'alink')
        ->assertJsonPath('transcript_download_url', 'tlink');

    $this->assertDatabaseHas('pending_recordings', [
        'id' => $pending->id,
        'status' => PendingRecording::STATUS_COMPLETED,
        'meeting_name' => 'Meeting.mp3',
        'audio_drive_id' => 'aud456',
    ]);
});

it('rejects manual subfolder parameters', function () {
    $user = User::factory()->create(['username' => 'manual-test']);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'rootXYZ',
        'name'            => 'Root XYZ',
        'parent_id'       => null,
    ]);

    $payload = [
        'meetingName' => 'Invalid meeting',
        'rootFolder' => 'rootXYZ',
        'transcriptionSubfolder' => 'should-not-pass',
        'audioSubfolder' => 'should-not-pass',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioData' => base64_encode('audio'),
        'audioMimeType' => 'audio/webm',
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['transcriptionSubfolder', 'audioSubfolder']);
});

it('ensures standard folders exist through the helper', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $rootFolder = Folder::create([
        'google_token_id' => 1,
        'google_id'       => 'rootABC',
        'name'            => 'Root ABC',
        'parent_id'       => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')
        ->once()->with('rootABC', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Transcripciones', 'rootABC')->ordered()->andReturn('transABC');
    $service->shouldReceive('shareFolder')
        ->once()->with('transABC', 'svc@example.com')->ordered();
    $service->shouldReceive('createFolder')
        ->once()->with('Audio', 'rootABC')->ordered()->andReturn('audioABC');
    $service->shouldReceive('shareFolder')
        ->once()->with('audioABC', 'svc@example.com')->ordered();

    $result = DriveController::ensureStandardMeetingFolders($rootFolder, $service);

    expect($result['transcriptions']['google_id'])->toBe('transABC');
    expect($result['audio']['google_id'])->toBe('audioABC');

    $this->assertDatabaseHas('subfolders', [
        'folder_id' => $rootFolder->id,
        'google_id' => 'transABC',
        'name'      => 'Transcripciones',
    ]);
    $this->assertDatabaseHas('subfolders', [
        'folder_id' => $rootFolder->id,
        'google_id' => 'audioABC',
        'name'      => 'Audio',
    ]);
});
