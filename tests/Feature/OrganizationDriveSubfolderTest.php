<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\GoogleToken;
use App\Models\OrganizationFolder;
use App\Services\GoogleDriveService;
use Google\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

it('allows collaborator to create organization subfolder', function () {
    Config::set('services.google.service_account_email', 'svc@test');

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

    $token = GoogleToken::create([
        'username'      => $admin->username,
        'access_token'  => 'access',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $root = OrganizationFolder::create([
        'organization_id' => $organization->id,
        'google_token_id' => $token->id,
        'google_id'       => 'root123',
        'name'            => 'Root',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleDriveService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldReceive('createFolder')->once()->with('SubFolder', 'root123')->andReturn('sub123');
    $service->shouldReceive('shareFolder')->once()->with('sub123', 'svc@test');

    app()->instance(GoogleDriveService::class, $service);

    $response = $this->actingAs($collab)->postJson(
        "/api/organizations/{$organization->id}/drive/subfolders",
        ['name' => 'SubFolder']
    );

    $response->assertStatus(201)->assertJsonStructure(['id']);

    $this->assertDatabaseHas('organization_subfolders', [
        'organization_folder_id' => $root->id,
        'google_id' => 'sub123',
        'name' => 'SubFolder',
    ]);
});

it('denies subfolder creation to non-collaborator', function () {
    $admin = User::factory()->create(['username' => 'admin']);
    $collab = User::factory()->create(['username' => 'collab']);
    $guest = User::factory()->create(['username' => 'guest']);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'desc',
        'num_miembros' => 0,
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $organization->users()->attach($collab->id, ['rol' => 'colaborador']);
    $organization->users()->attach($guest->id, ['rol' => 'invitado']);

    $response = $this->actingAs($guest)->postJson(
        "/api/organizations/{$organization->id}/drive/subfolders",
        ['name' => 'SubFolder']
    );

    $response->assertStatus(403);
});
