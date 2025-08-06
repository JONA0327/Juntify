<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Services\GoogleServiceAccount;
use Illuminate\Support\Facades\Config;
use Mockery;

it('shares both folders with the service account when saving results', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'testuser']);

    GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
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

    $response->assertOk();
});
