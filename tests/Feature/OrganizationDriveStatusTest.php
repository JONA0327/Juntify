<?php

use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationSubfolder;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;

uses(RefreshDatabase::class);

it('returns root information and standard folders', function () {
    Config::set('services.google.service_account_email', 'svc@test');

    $admin = User::factory()->create(['username' => 'admin']);
    $collab = User::factory()->create(['username' => 'collab']);

    $organization = Organization::factory()->create([
        'admin_id' => $admin->id,
    ]);

    $organization->users()->attach($admin->id, ['rol' => 'administrador']);
    $organization->users()->attach($collab->id, ['rol' => 'colaborador']);

    $token = OrganizationGoogleToken::factory()->create([
        'organization_id' => $organization->id,
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addHour(),
    ]);

    $root = OrganizationFolder::factory()->create([
        'organization_id' => $organization->id,
        'organization_google_token_id' => $token->id,
        'google_id' => 'root123',
        'name' => 'Root Folder',
    ]);

    OrganizationSubfolder::factory()->create([
        'organization_folder_id' => $root->id,
        'name' => 'Audio',
        'google_id' => 'audio123',
    ]);

    OrganizationSubfolder::factory()->create([
        'organization_folder_id' => $root->id,
        'name' => 'Transcripciones',
        'google_id' => 'trans123',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $driveService = Mockery::mock(GoogleDriveService::class);
    $driveService->shouldReceive('getClient')->andReturn($client);
    app()->instance(GoogleDriveService::class, $driveService);

    $serviceAccount = Mockery::mock(GoogleServiceAccount::class);
    $serviceAccount->shouldReceive('shareFolder')->atLeast()->once();
    $serviceAccount->shouldReceive('createFolder')->never();
    app()->instance(GoogleServiceAccount::class, $serviceAccount);

    $response = $this->actingAs($collab)
        ->getJson("/api/organizations/{$organization->id}/drive/status");

    $response->assertOk();
    $response->assertJsonPath('connected', true);
    $response->assertJsonPath('root_folder.google_id', 'root123');

    $standard = collect($response->json('standard_subfolders'));
    expect($standard->pluck('google_id')->sort()->values()->all())
        ->toEqualCanonicalizing(['audio123', 'trans123']);
});
