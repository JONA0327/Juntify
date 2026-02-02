<?php

// Script para verificar tokens de Google en la base de datos
// Ejecuta: php artisan tinker --execute="include 'check-google-tokens.php'"

echo "=== VERIFICACIÓN DE TOKENS DE GOOGLE ===\n\n";

use App\Models\GoogleToken;
use App\Models\User;

// Verificar si hay usuarios con tokens de Google
$users = User::whereHas('googleToken')->with('googleToken')->get();

if ($users->count() > 0) {
    echo "Usuarios con tokens de Google:\n";
    foreach ($users as $user) {
        $token = $user->googleToken;
        $hasAccess = $token && !empty($token->access_token);
        $hasRefresh = $token && !empty($token->refresh_token);
        $isExpired = $token && $token->expiry_date && $token->expiry_date->isPast();
        
        echo "\n📧 Usuario: {$user->email}\n";
        echo "   🔑 Access Token: " . ($hasAccess ? '✓ Presente' : '✗ Ausente') . "\n";
        echo "   🔄 Refresh Token: " . ($hasRefresh ? '✓ Presente' : '✗ Ausente') . "\n";
        echo "   ⏰ Expiración: " . ($token->expiry_date ? $token->expiry_date->format('Y-m-d H:i:s') : 'No definida') . "\n";
        echo "   📅 Estado: " . ($isExpired ? '🔴 EXPIRADO' : '🟢 VÁLIDO') . "\n";
        
        if ($token->recordings_folder_id) {
            echo "   📁 Carpeta Recordings: {$token->recordings_folder_id}\n";
        }
    }
} else {
    echo "ℹ️ No hay usuarios con tokens de Google configurados.\n";
    echo "   Para conectar Google Drive, ve a tu perfil y haz clic en 'Conectar Drive y Calendar'\n";
}

// Verificar tokens organizacionales
use App\Models\OrganizationGoogleToken;
$orgTokens = OrganizationGoogleToken::with('organization')->get();

if ($orgTokens->count() > 0) {
    echo "\n\n=== TOKENS ORGANIZACIONALES ===\n";
    foreach ($orgTokens as $orgToken) {
        $hasAccess = !empty($orgToken->access_token);
        $hasRefresh = !empty($orgToken->refresh_token);
        $isExpired = $orgToken->expiry_date && $orgToken->expiry_date->isPast();
        
        echo "\n🏢 Organización: {$orgToken->organization->name}\n";
        echo "   🔑 Access Token: " . ($hasAccess ? '✓ Presente' : '✗ Ausente') . "\n";
        echo "   🔄 Refresh Token: " . ($hasRefresh ? '✓ Presente' : '✗ Ausente') . "\n";
        echo "   ⏰ Expiración: " . ($orgToken->expiry_date ? $orgToken->expiry_date->format('Y-m-d H:i:s') : 'No definida') . "\n";
        echo "   📅 Estado: " . ($isExpired ? '🔴 EXPIRADO' : '🟢 VÁLIDO') . "\n";
    }
} else {
    echo "\n\nℹ️ No hay tokens organizacionales configurados.\n";
}

echo "\n\n=== PRÓXIMOS PASOS ===\n";
echo "1. Si no tienes tokens, ve a la aplicación web y conecta Google Drive\n";
echo "2. Si los tokens están expirados, desconecta y vuelve a conectar\n";
echo "3. La URL de conexión es: http://127.0.0.1:8000/auth/google/redirect\n";

echo "\n";