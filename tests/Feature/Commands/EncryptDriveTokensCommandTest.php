<?php

use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('encrypts legacy drive tokens stored as plain text', function () {
    $now = now()->toDateTimeString();

    $googleTokenId = DB::table('google_tokens')->insertGetId([
        'username' => 'legacy@example.com',
        'access_token' => 'legacy-access-token',
        'refresh_token' => 'legacy-refresh-token',
        'expiry_date' => $now,
        'recordings_folder_id' => 'legacy-folder-id',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $organizationId = DB::table('organizations')->insertGetId([
        'nombre_organizacion' => 'Legacy Org',
        'descripcion' => null,
        'imagen' => null,
        'num_miembros' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $legacyOrgAccessToken = [
        'access_token' => 'org-access-token',
        'refresh_token' => 'org-refresh-token',
        'expires_in' => 3600,
    ];

    DB::table('organization_google_tokens')->insert([
        'organization_id' => $organizationId,
        'access_token' => json_encode($legacyOrgAccessToken),
        'refresh_token' => 'org-plain-refresh',
        'expiry_date' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $exitCode = Artisan::call('encrypt:drive-tokens');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('GoogleToken actualizados: 1');
    expect($output)->toContain('OrganizationGoogleToken actualizados: 1');
    expect($output)->toContain('Total de registros actualizados: 2');

    $googleToken = GoogleToken::findOrFail($googleTokenId);

    expect(Crypt::decryptString($googleToken->getRawOriginal('access_token')))->toBe('legacy-access-token');
    expect(Crypt::decryptString($googleToken->getRawOriginal('refresh_token')))->toBe('legacy-refresh-token');
    expect(Crypt::decryptString($googleToken->getRawOriginal('recordings_folder_id')))->toBe('legacy-folder-id');

    expect($googleToken->access_token)->toBe('legacy-access-token');
    expect($googleToken->refresh_token)->toBe('legacy-refresh-token');
    expect($googleToken->recordings_folder_id)->toBe('legacy-folder-id');

    $organizationToken = OrganizationGoogleToken::where('organization_id', $organizationId)->firstOrFail();

    expect(Crypt::decryptString($organizationToken->getRawOriginal('access_token')))->toBe(json_encode($legacyOrgAccessToken));
    expect(Crypt::decryptString($organizationToken->getRawOriginal('refresh_token')))->toBe('org-plain-refresh');

    $decodedAccessToken = $organizationToken->access_token;
    expect($decodedAccessToken)->toBeArray();
    expect($decodedAccessToken['access_token'])->toBe('org-access-token');
    expect($decodedAccessToken['refresh_token'])->toBe('org-refresh-token');

    expect($organizationToken->refresh_token)->toBe('org-plain-refresh');
});
