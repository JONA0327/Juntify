<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationFolder;

try {
    DB::beginTransaction();

    $org = Organization::first();
    if (! $org) {
        $org = Organization::create([
            'nombre_organizacion' => 'Test Org',
            'descripcion' => 'Temp for insert check',
        ]);
        echo "Created organization ID {$org->id}\n";
    } else {
        echo "Using existing organization ID {$org->id}\n";
    }

    $tok = OrganizationGoogleToken::firstOrCreate(
        ['organization_id' => $org->id],
        ['access_token' => 'x', 'refresh_token' => 'x', 'expiry_date' => now()]
    );
    echo "Using token ID {$tok->id}\n";

    $googleId = 'test_' . Str::random(10);
    $folder = OrganizationFolder::create([
        'organization_id' => $org->id,
        'organization_google_token_id' => $tok->id,
        'google_id' => $googleId,
        'name' => 'Test Root Folder',
    ]);

    echo "Inserted folder ID {$folder->id} with google_id {$folder->google_id}\n";

    DB::rollBack(); // we don't want to persist test data
    echo "Rolled back test transaction.\n";

    echo "SUCCESS";
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
