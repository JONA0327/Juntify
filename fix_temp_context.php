<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CORRIGIENDO CONTEXTO DE REUNIONES TEMPORALES ===\n\n";

try {
    // Autenticar usuario
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    echo "Usuario encontrado:\n";
    echo "   - Email: {$user->email}\n";
    echo "   - Username: {$user->username}\n\n";

    echo "1. MODIFICANDO getMeetingIfAccessible PARA REUNIONES TEMPORALES:\n";
    echo "================================================================\n";

    // Ahora necesito modificar el método directamente en el archivo
    echo "✅ Voy a crear los métodos necesarios en AiAssistantController...\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
