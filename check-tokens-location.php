<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICACIÓN DE UBICACIÓN DE google_tokens ===\n\n";

// Verificar en juntify_new (conexión predeterminada)
try {
    $countNew = DB::table('google_tokens')->count();
    echo "✓ Tokens en juntify_new: $countNew\n";
    
    if ($countNew > 0) {
        $token = DB::table('google_tokens')->first();
        echo "  - Username: {$token->username}\n";
        echo "  - Expiry: {$token->expiry_date}\n\n";
    }
} catch (\Exception $e) {
    echo "✗ Error en juntify_new: " . $e->getMessage() . "\n\n";
}

// Verificar en juntify_panels
try {
    $countPanels = DB::connection('juntify_panels')->table('google_tokens')->count();
    echo "✓ Tokens en juntify_panels: $countPanels\n";
} catch (\Exception $e) {
    echo "✗ Error en juntify_panels: " . $e->getMessage() . "\n";
}
