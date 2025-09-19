<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\OrganizationFolder;
use App\Services\GoogleServiceAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

it('prevents collaborator from saving meeting to personal drive', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $admin = User::factory()->create(['username' => 'admin']);
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $collab = User::factory()->create([
        'username' => 'collab',
        'current_organization_id' => $organization->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $organization->users()->attach($collab->id, ['rol' => 'colaborador']);

    $token = GoogleToken::create([
        'username'      => $admin->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    OrganizationFolder::create([
        'organization_id' => $organization->id,
        'google_token_id' => $token->id,
        'google_id'       => 'orgRoot123',
        'name'            => 'OrgRoot',
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'personal123',
        'transcriptionData' => json_encode([
            ['end' => 1, 'speaker' => 'A'],
        ]),
        'analysisResults' => json_encode([
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ]),
        'audioFile' => UploadedFile::fake()->createWithContent('audio.ogg', 'audio', 'audio/ogg'),
    ];

    $response = $this->actingAs($collab)->post('/drive/save-results', $payload);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Colaboradores solo pueden usar la carpeta de la organización']);
});

it('allows administrator to save meeting to personal drive', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $admin = User::factory()->create(['username' => 'admin']);
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $admin->update(['current_organization_id' => $organization->id]);

    $token = GoogleToken::create([
        'username'      => $admin->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id'       => 'personal123',
        'name'            => 'PersonalRoot',
        'parent_id'       => null,
    ]);

    OrganizationFolder::create([
        'organization_id' => $organization->id,
        'google_token_id' => $token->id,
        'google_id'       => 'orgRoot123',
        'name'            => 'OrgRoot',
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')->twice();
    $service->shouldReceive('uploadFile')->twice()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')->twice()->andReturn('tlink', 'alink');
    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'personal123',
        'transcriptionData' => json_encode([
            ['end' => 1, 'speaker' => 'A'],
        ]),
        'analysisResults' => json_encode([
            'summary' => 'sum',
            'keyPoints' => [],
            'tasks' => [],
        ]),
        'audioFile' => UploadedFile::fake()->createWithContent('audio.ogg', 'audio', 'audio/ogg'),
    ];

    $response = $this->actingAs($admin)->post('/drive/save-results', $payload);

    $response->assertOk();
});

it('allows administrator to save meeting to organization drive', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $admin = User::factory()->create(['username' => 'admin']);
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $admin->update(['current_organization_id' => $organization->id]);

    $token = GoogleToken::create([
        'username'      => $admin->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    OrganizationFolder::create([
        'organization_id' => $organization->id,
        'google_token_id' => $token->id,
        'google_id'       => 'orgRoot123',
        'name'            => 'OrgRoot',
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')->twice();
    $service->shouldReceive('uploadFile')->twice()->andReturn('t1', 'a1');
    $service->shouldReceive('getFileLink')->twice()->andReturn('tlink', 'alink');
    app()->instance(GoogleServiceAccount::class, $service);

    $payload = [
        'meetingName' => 'Meeting',
        'rootFolder' => 'orgRoot123',
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

    $response = $this->actingAs($admin)->post('/drive/save-results', $payload);

    $response->assertOk();
});

