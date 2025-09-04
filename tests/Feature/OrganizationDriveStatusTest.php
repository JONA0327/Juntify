<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\GoogleToken;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Services\GoogleDriveService;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

it('returns root and subfolders for collaborator', function () {
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

    OrganizationSubfolder::create([
        'organization_folder_id' => $root->id,
        'google_id' => 'sub123',
        'name' => 'Sub',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $fileMock = new class {
        public function getId() { return 'sub123'; }
        public function getName() { return 'Sub'; }
    };

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('getClient')->andReturn($client);
    $drive->shouldReceive('listSubfolders')->once()->with('root123')->andReturn([$fileMock]);

    app()->instance(GoogleDriveService::class, $drive);

    $response = $this->actingAs($collab)->getJson("/api/organizations/{$organization->id}/drive/status");

    $response->assertOk()
        ->assertJson([
            'connected' => true,
            'root_folder' => ['google_id' => 'root123'],
        ]);

    $this->assertEquals('sub123', $response->json('subfolders.0.google_id'));
});
