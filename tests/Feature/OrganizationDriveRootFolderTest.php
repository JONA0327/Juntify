<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationFolder;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;

uses(RefreshDatabase::class);

it('allows administrator to create organization root folder', function () {
    Config::set('services.google.service_account_email', 'svc@test');
    Config::set('drive.root_folder_id', 'drive-root');

    $admin = User::factory()->create(['username' => 'admin']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);

    $token = OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('getClient')->andReturn($client);
    $drive->shouldNotReceive('createFolder');
    $drive->shouldReceive('shareFolder')->never();

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('Org', 'drive-root')
        ->once()
        ->andReturn('root123');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios', 'root123')
        ->andReturn('audios');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Transcripciones', 'root123')
        ->andReturn('transcripciones');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios Pospuestos', 'root123')
        ->andReturn('pospuestos');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Documentos', 'root123')
        ->andReturn('documentos');
    $serviceAccount->shouldReceive('shareFolder')->andReturnNull();

    app()->instance(GoogleDriveService::class, $drive);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($admin)->postJson("/api/organizations/{$organization->id}/drive/root-folder");

    $response->assertStatus(201)->assertJson(['id' => 'root123']);

    $this->assertDatabaseHas('organization_folders', [
        'organization_id' => $organization->id,
        'google_id' => 'root123',
        'name' => 'Org',
    ]);
});

it('falls back to oauth client when service account cannot create root folder', function () {
    Config::set('services.google.service_account_email', 'svc@test');
    Config::set('drive.root_folder_id', 'drive-root');

    $admin = User::factory()->create(['username' => 'admin', 'email' => 'admin@test']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);

    $token = OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('getClient')->andReturn($client);
    $drive->shouldReceive('createFolder')->once()->with('Org', 'drive-root')->andReturn('root123');
    $drive->shouldReceive('shareFolder')->once()->with('root123', 'svc@test');

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('createFolder')
        ->with('Org', 'drive-root')
        ->twice()
        ->andThrow(new RuntimeException('forbidden'));
    $serviceAccount->shouldReceive('impersonate')->once()->with('admin@test');
    $serviceAccount->shouldReceive('impersonate')->once()->with(null);
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios', 'root123')
        ->andReturn('audios');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Transcripciones', 'root123')
        ->andReturn('transcripciones');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Audios Pospuestos', 'root123')
        ->andReturn('pospuestos');
    $serviceAccount->shouldReceive('createFolder')
        ->with('Documentos', 'root123')
        ->andReturn('documentos');
    $serviceAccount->shouldReceive('shareFolder')->andReturnNull();

    app()->instance(GoogleDriveService::class, $drive);
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($admin)->postJson("/api/organizations/{$organization->id}/drive/root-folder");

    $response->assertStatus(201)->assertJson(['id' => 'root123']);

    $this->assertDatabaseHas('organization_folders', [
        'organization_id' => $organization->id,
        'google_id' => 'root123',
        'name' => 'Org',
    ]);

    $this->assertDatabaseHas('organization_google_tokens', [
        'id' => $token->id,
        'organization_id' => $organization->id,
    ]);
});

it('denies root folder creation to collaborator', function () {
    $admin = User::factory()->create(['username' => 'admin']);
    $collab = User::factory()->create(['username' => 'collab']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $organization->users()->attach($collab->id, ['rol' => 'colaborador']);

    $response = $this->actingAs($collab)->postJson("/api/organizations/{$organization->id}/drive/root-folder");

    $response->assertStatus(403);
});

it('returns an error when the drive root folder id is missing', function () {
    Config::set('drive.root_folder_id', null);

    $admin = User::factory()->create(['username' => 'admin']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);

    $response = $this->actingAs($admin)->postJson("/api/organizations/{$organization->id}/drive/root-folder");

    $response->assertStatus(500)
        ->assertJson([
            'message' => 'El ID de la carpeta raíz de Google Drive no está configurado.',
        ]);
});

