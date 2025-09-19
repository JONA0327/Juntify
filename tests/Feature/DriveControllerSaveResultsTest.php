<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\OrganizationGoogleToken;
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

it('impersonates the personal drive owner when creating missing subfolders', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create([
        'username' => 'impersonate-user',
        'email' => 'owner@example.com',
    ]);

    $token = GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $rootFolder = Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'root123',
        'name'            => 'Root',
        'parent_id'       => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('impersonate')->with('owner@example.com')->once()->ordered('audios');
    $service->shouldReceive('createFolder')->with('Audios', 'root123')->once()->ordered('audios')->andReturn('audios-id');
    $service->shouldReceive('impersonate')->with('owner@example.com')->once()->ordered('trans');
    $service->shouldReceive('createFolder')->with('Transcripciones', 'root123')->once()->ordered('trans')->andReturn('trans-id');
    $service->shouldReceive('impersonate')->with('owner@example.com')->once()->ordered('pending');
    $service->shouldReceive('createFolder')->with('Audios Pospuestos', 'root123')->once()->ordered('pending')->andReturn('pending-id');
    $service->shouldReceive('impersonate')->with(null)->once();
    $service->shouldReceive('shareFolder')->zeroOrMoreTimes()->andReturnNull();
    $service->shouldReceive('uploadFile')->twice()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')->twice()->andReturn('tlink', 'alink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioFile' => UploadedFile::fake()->createWithContent('audio.ogg', 'audio-bytes', 'audio/ogg'),
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();

    $subfolders = Subfolder::where('folder_id', $rootFolder->id)->get();
    expect($subfolders)->toHaveCount(3);
    expect($subfolders->pluck('folder_id')->unique()->all())->toEqual([$rootFolder->id]);
    expect($subfolders->pluck('name')->sort()->values()->all())->toEqual(['Audios', 'Audios Pospuestos', 'Transcripciones']);
});

it('impersonates the organization connector when creating missing subfolders', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $admin = User::factory()->create([
        'username' => 'org-admin',
        'email' => 'admin@example.com',
    ]);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'Desc',
        'imagen' => null,
        'num_miembros' => 1,
        'admin_id' => $admin->id,
    ]);

    $token = OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addHour(),
    ]);

    $rootFolder = OrganizationFolder::create([
        'organization_id' => $organization->id,
        'organization_google_token_id' => $token->id,
        'google_id' => 'org-root',
        'name' => 'Org Root',
    ]);

    $user = User::factory()->create([
        'username' => 'member',
        'email' => 'member@example.com',
        'current_organization_id' => $organization->id,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('impersonate')->with('admin@example.com')->once()->ordered('audios');
    $service->shouldReceive('createFolder')->with('Audios', 'org-root')->once()->ordered('audios')->andReturn('org-audios');
    $service->shouldReceive('impersonate')->with('admin@example.com')->once()->ordered('trans');
    $service->shouldReceive('createFolder')->with('Transcripciones', 'org-root')->once()->ordered('trans')->andReturn('org-trans');
    $service->shouldReceive('impersonate')->with('admin@example.com')->once()->ordered('pending');
    $service->shouldReceive('createFolder')->with('Audios Pospuestos', 'org-root')->once()->ordered('pending')->andReturn('org-pending');
    $service->shouldReceive('impersonate')->with(null)->once();
    $service->shouldReceive('shareFolder')->zeroOrMoreTimes()->andReturnNull();
    $service->shouldReceive('uploadFile')->twice()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')->twice()->andReturn('tlink', 'alink');

    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'driveType' => 'organization',
        'transcriptionData' => [
            ['end' => 1, 'speaker' => 'A'],
        ],
        'analysisResults' => [
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ],
        'audioFile' => UploadedFile::fake()->createWithContent('audio.ogg', 'audio-bytes', 'audio/ogg'),
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();

    $subfolders = OrganizationSubfolder::where('organization_folder_id', $rootFolder->id)->get();
    expect($subfolders)->toHaveCount(3);
    expect($subfolders->pluck('organization_folder_id')->unique()->all())->toEqual([$rootFolder->id]);
    expect($subfolders->pluck('name')->sort()->values()->all())->toEqual(['Audios', 'Audios Pospuestos', 'Transcripciones']);
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

it('allows uploading audio files with newly supported mime types', function () {
    Config::set('services.google.service_account_email', 'svc@example.com');

    $user = User::factory()->create(['username' => 'mime-user']);

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
        ->once()->with('Meeting.opus', 'audio/opus', 'audio123', Mockery::type('string'))->ordered()->andReturn('a1');
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
        'audioFile' => UploadedFile::fake()->createWithContent('audio.opus', 'audio-bytes', 'audio/opus'),
    ];

    $response = $this->actingAs($user)->post('/drive/save-results', $payload);

    $response->assertOk();
});
