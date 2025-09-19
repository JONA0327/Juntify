<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Services\GoogleServiceAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Mockery;

it('shares both folders with the service account when saving results', function () {
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
        ->once()->with('trans123', 'svc@example.com')->ordered();
    $service->shouldReceive('shareFolder')
        ->once()->with('audio123', 'svc@example.com')->ordered();
    $service->shouldReceive('uploadFile')
        ->twice()->ordered()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')
        ->twice()->ordered()->andReturn('tlink', 'alink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'root123',
        'transcriptionSubfolder' => 'trans123',
        'audioSubfolder' => 'audio123',
        'transcriptionData' => json_encode([
            ['end' => 1, 'speaker' => 'A'],
        ]),
        'analysisResults' => json_encode([
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ]),
        'audioFile' => UploadedFile::fake()->createWithContent('audio.ogg', 'audio-bytes', 'audio/ogg'),
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();
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
        ->once()->with('trans123', 'svc@example.com')->ordered();
    $service->shouldReceive('shareFolder')
        ->once()->with('audio123', 'svc@example.com')->ordered();
    $service->shouldReceive('uploadFile')
        ->once()->with('Meeting.ju', 'application/json', 'trans123', Mockery::type('string'))->ordered()->andReturn('t1');
    $service->shouldReceive('uploadFile')
        ->once()->with('Meeting.ogg', 'audio/ogg', 'audio123', Mockery::type('string'))->ordered()->andReturn('a1');
    $service->shouldReceive('getFileLink')
        ->twice()->ordered()->andReturn('tlink', 'alink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'root123',
        'transcriptionSubfolder' => 'trans123',
        'audioSubfolder' => 'audio123',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioData' => base64_encode('audio'),
        'audioMimeType' => 'audio/ogg',
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();
});
