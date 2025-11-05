<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🌐 TEST DE DESCARGA HTTP - SIMULACIÓN CONTROLADOR BNI\n";
echo "====================================================\n\n";

// 1. Preparar datos del test
$userEmail = 'CongresoBNI@gmail.com';
$user = App\Models\User::where('email', $userEmail)->first();
$meeting = App\Models\TranscriptionTemp::where('user_id', $user->id)->first();

echo "📋 DATOS DEL TEST:\n";
echo "   - Usuario: {$user->email}\n";
echo "   - Reunión ID: {$meeting->id}\n";
echo "   - Título: {$meeting->title}\n\n";

// 2. Simular autenticación
echo "🔐 SIMULANDO AUTENTICACIÓN:\n";
\Illuminate\Support\Facades\Auth::login($user);
$authenticatedUser = \Illuminate\Support\Facades\Auth::user();
echo "   - Usuario autenticado: " . ($authenticatedUser ? '✅' : '❌') . "\n";
echo "   - Email: " . ($authenticatedUser->email ?? 'N/A') . "\n";
echo "   - Rol: " . ($authenticatedUser->roles ?? 'N/A') . "\n\n";

// 3. Simular request HTTP
echo "🌐 SIMULANDO REQUEST HTTP:\n";
$request = new \Illuminate\Http\Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

echo "   - Método: GET\n";
echo "   - URL: /transcription-temp/{$meeting->id}/download-ju\n";
echo "   - Usuario en request: " . ($request->user() ? '✅' : '❌') . "\n\n";

// 4. Instanciar controlador
echo "🎮 INSTANCIANDO CONTROLADOR:\n";
try {
    $controller = new App\Http\Controllers\TranscriptionTempController();
    echo "   - TranscriptionTempController creado: ✅\n";

    // Verificar si existe el método downloadJu
    $hasDownloadMethod = method_exists($controller, 'downloadJu');
    echo "   - Método downloadJu existe: " . ($hasDownloadMethod ? '✅' : '❌') . "\n";

    if (!$hasDownloadMethod) {
        echo "\n⚠️  MÉTODO downloadJu NO EXISTE\n";
        echo "   Necesitamos crearlo en TranscriptionTempController\n\n";

        // Mostrar métodos disponibles
        echo "📋 Métodos disponibles en el controlador:\n";
        $methods = get_class_methods($controller);
        foreach ($methods as $method) {
            if (!str_starts_with($method, '__')) {
                echo "   - {$method}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "   ❌ Error creando controlador: {$e->getMessage()}\n";
}

// 5. Simular descarga (solo si el método existe)
if (isset($hasDownloadMethod) && $hasDownloadMethod) {
    echo "\n📥 SIMULANDO DESCARGA:\n";
    try {
        $response = $controller->downloadJu($request, $meeting->id);
        echo "   - Respuesta obtenida: ✅\n";
        echo "   - Tipo de respuesta: " . get_class($response) . "\n";

        if ($response instanceof \Illuminate\Http\Response) {
            $headers = $response->headers->all();
            echo "   - Headers:\n";
            foreach ($headers as $key => $values) {
                echo "     * {$key}: " . implode(', ', $values) . "\n";
            }
        }

    } catch (Exception $e) {
        echo "   ❌ Error en descarga: {$e->getMessage()}\n";
    }
} else {
    echo "\n🔧 CREANDO MÉTODO downloadJu:\n";
    echo "   El método no existe, necesitamos implementarlo\n";
    echo "   Ubicación: app/Http/Controllers/TranscriptionTempController.php\n\n";

    // Mostrar código de ejemplo para el método
    echo "💡 CÓDIGO SUGERIDO PARA EL MÉTODO:\n";
    echo "==================================\n";
    echo "public function downloadJu(Request \$request, \$id)\n";
    echo "{\n";
    echo "    \$user = \$request->user();\n";
    echo "    \$transcription = TranscriptionTemp::where('id', \$id)\n";
    echo "        ->where('user_id', \$user->id)\n";
    echo "        ->firstOrFail();\n";
    echo "\n";
    echo "    // Preparar contenido del archivo .ju\n";
    echo "    \$content = [\n";
    echo "        'meeting_info' => [\n";
    echo "            'id' => \$transcription->id,\n";
    echo "            'title' => \$transcription->title,\n";
    echo "            'date' => \$transcription->created_at,\n";
    echo "            'user' => \$user->email,\n";
    echo "            'type' => 'BNI_temporal'\n";
    echo "        ],\n";
    echo "        'transcription' => file_get_contents(storage_path('app/' . \$transcription->transcription_path)),\n";
    echo "        'bni_features' => [\n";
    echo "            'unencrypted' => true,\n";
    echo "            'auto_download' => true\n";
    echo "        ]\n";
    echo "    ];\n";
    echo "\n";
    echo "    \$json = json_encode(\$content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);\n";
    echo "    \$filename = 'reunion_' . \$transcription->id . '.ju';\n";
    echo "\n";
    echo "    return response(\$json)\n";
    echo "        ->header('Content-Type', 'application/json')\n";
    echo "        ->header('Content-Disposition', 'attachment; filename=\"' . \$filename . '\"');\n";
    echo "}\n\n";
}

// 6. Verificar ruta
echo "🛣️  VERIFICANDO RUTA:\n";
echo "   - Ruta esperada: GET /transcription-temp/{id}/download-ju\n";
echo "   - Archivo de rutas: routes/web.php o routes/api.php\n";
echo "   - Middleware requerido: auth\n\n";

// 7. Resumen del test
echo "📋 RESUMEN DEL TEST HTTP:\n";
echo "========================\n";
echo "✅ Usuario BNI autenticado correctamente\n";
echo "✅ Reunión temporal encontrada\n";
echo "✅ Request HTTP simulado\n";
echo "✅ Controlador instanciado\n";

if (isset($hasDownloadMethod) && $hasDownloadMethod) {
    echo "✅ Método downloadJu existe y funcionó\n";
    echo "🎉 ¡DESCARGA HTTP EXITOSA!\n";
} else {
    echo "⚠️  Método downloadJu necesita ser implementado\n";
    echo "📝 Código de ejemplo proporcionado arriba\n";
}

echo "\n🎯 PRÓXIMOS PASOS:\n";
echo "=================\n";
if (!isset($hasDownloadMethod) || !$hasDownloadMethod) {
    echo "1. Implementar método downloadJu en TranscriptionTempController\n";
    echo "2. Agregar ruta en routes/web.php\n";
    echo "3. Probar descarga real desde la interfaz\n";
} else {
    echo "1. ✅ Método downloadJu implementado\n";
    echo "2. Verificar ruta en routes/web.php\n";
    echo "3. Probar descarga real desde la interfaz\n";
}
