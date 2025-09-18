<?php

use App\Models\Folder;
use App\Models\GoogleToken;
use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\User;
use App\Services\GoogleServiceAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Mockery;
use ReflectionProperty;

uses(RefreshDatabase::class);

it('returns 403 when selecting organization drive without proper role for pending uploads', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $admin = User::factory()->create();
    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);

    $member = User::factory()->create([
        'current_organization_id' => $organization->id,
    ]);

    $organization->users()->attach($member->id, ['rol' => 'invitado']);

    $token = GoogleToken::create([
        'username' => $admin->username,
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addHour(),
    ]);

    OrganizationFolder::create([
        'organization_id' => $organization->id,
        'google_token_id' => $token->id,
        'google_id' => 'orgRoot123',
        'name' => 'OrgRoot',
    ]);

    $audioFile = UploadedFile::fake()->create('audio.mp3', 10, 'audio/mpeg');

    $response = $this
        ->actingAs($member)
        ->post('/api/drive/upload-pending-audio', [
            'meetingName' => 'Test Meeting',
            'driveType' => 'organization',
            'audioFile' => $audioFile,
        ], ['HTTP_ACCEPT' => 'application/json']);

    $response
        ->assertStatus(403)
        ->assertJson([
            'message' => 'No tienes permisos para usar Drive organizacional',
        ]);
});

it('accepts pending audio uploads up to 500MB on personal drive', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $user = User::factory()->create();

    $token = GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addHour(),
    ]);

    $rootFolder = Folder::create([
        'google_token_id' => $token->id,
        'google_id' => 'root123',
        'name' => 'Grabaciones',
        'parent_id' => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')->once()->with('root123', 'svc@test');
    $service->shouldReceive('createFolder')->once()->with('Audios Pospuestos', 'root123')->andReturn('pending123');
    $service->shouldReceive('uploadFile')
        ->once()
        ->with('Big Meeting.mp3', 'audio/mpeg', 'pending123', Mockery::type('string'))
        ->andReturn('file123');
    $service->shouldReceive('getFileLink')->once()->with('file123')->andReturn('https://drive.test/file123');

    app()->instance(GoogleServiceAccount::class, $service);

    $audioFile = UploadedFile::fake()->create('audio.mp3', 1, 'audio/mpeg');
    $sizeProperty = new ReflectionProperty($audioFile, 'size');
    $sizeProperty->setAccessible(true);
    $sizeProperty->setValue($audioFile, 500 * 1024 * 1024);

    $response = $this
        ->actingAs($user)
        ->post('/api/drive/upload-pending-audio', [
            'meetingName' => 'Big Meeting',
            'audioFile' => $audioFile,
        ], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('audio_drive_id', 'file123')
        ->assertJsonPath('saved', true);

    $this->assertDatabaseHas('pending_recordings', [
        'audio_drive_id' => 'file123',
        'username' => $user->username,
    ]);

    $this->assertDatabaseHas('subfolders', [
        'folder_id' => $rootFolder->id,
        'name' => 'Audios Pospuestos',
        'google_id' => 'pending123',
    ]);
});

it('accepts pending audio uploads in m4a format on personal drive', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $user = User::factory()->create();

    $token = GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addHour(),
    ]);

    Folder::create([
        'google_token_id' => $token->id,
        'google_id' => 'root123',
        'name' => 'Grabaciones',
        'parent_id' => null,
    ]);

    $service = Mockery::mock(GoogleServiceAccount::class);
    $service->shouldReceive('shareFolder')->once()->with('root123', 'svc@test');
    $service->shouldReceive('createFolder')->once()->with('Audios Pospuestos', 'root123')->andReturn('pending123');
    $service->shouldReceive('uploadFile')
        ->once()
        ->with('Meeting.m4a', 'audio/x-m4a', 'pending123', Mockery::type('string'))
        ->andReturn('file456');
    $service->shouldReceive('getFileLink')->once()->with('file456')->andReturn('https://drive.test/file456');

    app()->instance(GoogleServiceAccount::class, $service);

    $audioFile = UploadedFile::fake()->create('Meeting.m4a', 1, 'audio/x-m4a');

    $response = $this
        ->actingAs($user)
        ->post('/api/drive/upload-pending-audio', [
            'meetingName' => 'Meeting',
            'audioFile' => $audioFile,
        ], ['HTTP_ACCEPT' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('audio_drive_id', 'file456')
        ->assertJsonPath('saved', true);
});
