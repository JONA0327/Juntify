<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ESTRUCTURA DE google_tokens ===\n\n";

try {
    // Verificar tabla google_tokens
    $columns = DB::connection('juntify_panels')
        ->getSchemaBuilder()
        ->getColumnListing('google_tokens');
    
    echo "Columnas de google_tokens:\n";
    print_r($columns);
    
    // Verificar si hay datos
    $count = DB::connection('juntify_panels')
        ->table('google_tokens')
        ->count();
    
    echo "\nTotal de tokens: $count\n\n";
    
    // Mostrar muestra de datos
    if ($count > 0) {
        $tokens = DB::connection('juntify_panels')
            ->table('google_tokens')
            ->select('id', 'user_id', 'expires_at', 'created_at')
            ->limit(3)
            ->get();
        
        echo "Muestra de tokens:\n";
        foreach ($tokens as $token) {
            echo "- ID: {$token->id}, User: {$token->user_id}, Expira: {$token->expires_at}\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
