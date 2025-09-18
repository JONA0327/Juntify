<?php

use App\Models\GoogleToken;
use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

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
