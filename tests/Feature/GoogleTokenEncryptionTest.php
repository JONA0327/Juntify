<?php

use App\Console\Commands\EncryptGoogleTokens;
use App\Models\GoogleToken;
use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('google token attributes are encrypted on save', function () {
    $user = User::factory()->create();

    $token = GoogleToken::create([
        'username' => $user->username,
        'access_token' => 'plain-access-token',
        'refresh_token' => 'plain-refresh-token',
    ]);

    $rawAccess = $token->getRawOriginal('access_token');
    $rawRefresh = $token->getRawOriginal('refresh_token');

    expect($rawAccess)->not->toBe('plain-access-token');
    expect($rawRefresh)->not->toBe('plain-refresh-token');
    expect(Crypt::decryptString($rawAccess))->toBe('plain-access-token');
    expect(Crypt::decryptString($rawRefresh))->toBe('plain-refresh-token');

    expect($token->access_token)->toBe('plain-access-token');
    expect($token->refresh_token)->toBe('plain-refresh-token');
});

test('organization token attributes are encrypted on save', function () {
    $organization = Organization::create([
        'nombre_organizacion' => 'Test Org',
        'descripcion' => 'Desc',
        'num_miembros' => 0,
    ]);

    $token = OrganizationGoogleToken::create([
        'organization_id' => $organization->id,
        'access_token' => 'org-access-token',
        'refresh_token' => 'org-refresh-token',
        'expiry_date' => now()->addHour(),
    ]);

    $rawAccess = $token->getRawOriginal('access_token');
    $rawRefresh = $token->getRawOriginal('refresh_token');

    expect($rawAccess)->not->toBe('org-access-token');
    expect($rawRefresh)->not->toBe('org-refresh-token');
    expect(Crypt::decryptString($rawAccess))->toBe('org-access-token');
    expect(Crypt::decryptString($rawRefresh))->toBe('org-refresh-token');

    expect($token->getTokenArray()['access_token'] ?? null)->toBe('org-access-token');
    expect($token->getTokenArray()['refresh_token'] ?? null)->toBe('org-refresh-token');
});

test('command encrypts legacy plain tokens', function () {
    $user = User::factory()->create(['username' => 'legacy-user']);
    DB::table('google_tokens')->insert([
        'username' => $user->username,
        'access_token' => 'legacy-access',
        'refresh_token' => 'legacy-refresh',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization = Organization::create([
        'nombre_organizacion' => 'Org',
        'descripcion' => 'Desc',
        'num_miembros' => 0,
    ]);

    DB::table('organization_google_tokens')->insert([
        'organization_id' => $organization->id,
        'access_token' => json_encode(['access_token' => 'legacy-org-access']),
        'refresh_token' => 'legacy-org-refresh',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('google:encrypt-existing-tokens')
        ->assertExitCode(EncryptGoogleTokens::SUCCESS);

    $userToken = GoogleToken::where('username', 'legacy-user')->first();
    $orgToken = OrganizationGoogleToken::where('organization_id', $organization->id)->first();

    $rawAccessUser = $userToken->getRawOriginal('access_token');
    $rawRefreshUser = $userToken->getRawOriginal('refresh_token');
    $rawAccessOrg = $orgToken->getRawOriginal('access_token');
    $rawRefreshOrg = $orgToken->getRawOriginal('refresh_token');

    expect(Crypt::decryptString($rawAccessUser))->toBe('legacy-access');
    expect(Crypt::decryptString($rawRefreshUser))->toBe('legacy-refresh');
    expect(Crypt::decryptString($rawAccessOrg))->toBe(json_encode(['access_token' => 'legacy-org-access']));
    expect(Crypt::decryptString($rawRefreshOrg))->toBe('legacy-org-refresh');

    expect($userToken->access_token)->toBe('legacy-access');
    expect($userToken->refresh_token)->toBe('legacy-refresh');
    expect($orgToken->getTokenArray()['access_token'] ?? null)->toBe('legacy-org-access');
    expect($orgToken->getTokenArray()['refresh_token'] ?? null)->toBe('legacy-org-refresh');
});
