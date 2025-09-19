<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationFolder;
use App\Services\GoogleDriveService;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

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
    $drive->shouldReceive('createFolder')->once()->with('Org', 'drive-root')->andReturn('root123');
    $drive->shouldReceive('shareFolder')->once()->with('root123', 'svc@test');

    app()->instance(GoogleDriveService::class, $drive);

    $response = $this->actingAs($admin)->postJson("/api/organizations/{$organization->id}/drive/root-folder");

    $response->assertStatus(201)->assertJson(['id' => 'root123']);

    $this->assertDatabaseHas('organization_folders', [
        'organization_id' => $organization->id,
        'google_id' => 'root123',
        'name' => 'Org',
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

