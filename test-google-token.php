<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== BÚSQUEDA DE TOKEN ===\n\n";

$username = 'Jona0327';

// Buscar token
$token = DB::connection('juntify_panels')
    ->table('google_tokens')
    ->where('username', $username)
    ->first();

if ($token) {
    echo "✅ Token encontrado:\n";
    echo "ID: {$token->id}\n";
    echo "Username: {$token->username}\n";
    echo "Expiry: {$token->expiry_date}\n";
    echo "Expires in: {$token->expires_in}\n";
} else {
    echo "❌ Token NO encontrado\n\n";
    
    // Ver todos los tokens
    $allTokens = DB::connection('juntify_panels')
        ->table('google_tokens')
        ->select('id', 'username')
        ->get();
    
    echo "Tokens disponibles:\n";
    foreach ($allTokens as $t) {
        echo "- ID: {$t->id}, Username: '{$t->username}'\n";
    }
}
