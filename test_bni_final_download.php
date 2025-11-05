<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🎯 TEST FINAL DE DESCARGA .JU BNI - USANDO RUTA REAL\n";
echo "===================================================\n\n";

// 1. Preparar datos
$userEmail = 'CongresoBNI@gmail.com';
$user = App\Models\User::where('email', $userEmail)->first();
$meeting = App\Models\TranscriptionTemp::where('user_id', $user->id)->first();

echo "📋 CONFIGURACIÓN DEL TEST:\n";
echo "   - Usuario: {$user->email} (Rol: {$user->roles})\n";
echo "   - Reunión: {$meeting->title} (ID: {$meeting->id})\n";
echo "   - Tipo: Almacenamiento temporal BNI\n\n";

// 2. Verificar que es usuario BNI
echo "🏷️  VERIFICACIÓN USUARIO BNI:\n";
$isBniUser = ($user->roles === 'BNI' || $user->plan === 'BNI');
echo "   - Es usuario BNI: " . ($isBniUser ? '✅ SÍ' : '❌ NO') . "\n";
echo "   - Tiene privilegios especiales: " . ($isBniUser ? '✅ SÍ' : '❌ NO') . "\n\n";

// 3. Verificar archivos
echo "📁 VERIFICACIÓN DE ARCHIVOS:\n";
$transcriptionExists = !empty($meeting->transcription_path) &&
                      \Illuminate\Support\Facades\Storage::disk('local')->exists($meeting->transcription_path);
$audioExists = !empty($meeting->audio_path) &&
               \Illuminate\Support\Facades\Storage::disk('local')->exists($meeting->audio_path);

echo "   - Archivo de transcripción: " . ($transcriptionExists ? '✅ Existe' : '❌ No existe') . "\n";
echo "   - Archivo de audio: " . ($audioExists ? '✅ Existe' : '❌ No existe') . "\n";

if ($transcriptionExists) {
    $transcriptionSize = \Illuminate\Support\Facades\Storage::disk('local')->size($meeting->transcription_path);
    echo "   - Tamaño transcripción: " . number_format($transcriptionSize) . " bytes\n";
}

if ($audioExists) {
    $audioSize = \Illuminate\Support\Facades\Storage::disk('local')->size($meeting->audio_path);
    echo "   - Tamaño audio: " . number_format($audioSize) . " bytes\n";
}

// 4. Simular llamada al controlador
echo "\n🎮 TEST DEL CONTROLADOR:\n";

try {
    // Autenticar usuario
    \Illuminate\Support\Facades\Auth::login($user);

    // Crear controlador
    $controller = new App\Http\Controllers\TranscriptionTempController();

    echo "   - Usuario autenticado: ✅\n";
    echo "   - Controlador creado: ✅\n";

    // Llamar al método downloadJuFile
    $response = $controller->downloadJuFile($meeting);

    echo "   - Método ejecutado: ✅\n";
    echo "   - Tipo de respuesta: " . get_class($response) . "\n";

    // Verificar headers de descarga
    $headers = $response->headers;
    $contentType = $headers->get('Content-Type');
    $contentDisposition = $headers->get('Content-Disposition');

    echo "   - Content-Type: {$contentType}\n";
    echo "   - Content-Disposition: {$contentDisposition}\n";

    // Verificar que es una descarga
    $isDownload = str_contains($contentDisposition, 'attachment');
    echo "   - Es descarga: " . ($isDownload ? '✅ SÍ' : '❌ NO') . "\n";

    // Verificar contenido
    $content = $response->getContent();
    $contentLength = strlen($content);
    echo "   - Tamaño respuesta: " . number_format($contentLength) . " bytes\n";

    // Verificar que es JSON válido (archivo .ju sin encriptar)
    $isValidJson = json_decode($content) !== null;
    echo "   - JSON válido (sin encriptar): " . ($isValidJson ? '✅ SÍ' : '❌ NO') . "\n";

    // Si es JSON, mostrar estructura
    if ($isValidJson) {
        $jsonData = json_decode($content, true);
        echo "   - Contiene claves: " . implode(', ', array_keys($jsonData)) . "\n";
    }

} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

// 5. Información de la ruta
echo "\n🛣️  INFORMACIÓN DE LA RUTA:\n";
echo "   - Ruta API: GET /api/transcriptions-temp/{$meeting->id}/download-ju\n";
echo "   - Nombre: api.transcriptions-temp.download-ju\n";
echo "   - Middleware: auth (requerido)\n";
echo "   - Método: TranscriptionTempController@downloadJuFile\n\n";

// 6. Verificar expiracion
echo "⏰ VERIFICACIÓN DE EXPIRACIÓN:\n";
$isExpired = $meeting->expires_at && $meeting->expires_at->isPast();
echo "   - Fecha de expiración: " . ($meeting->expires_at ?? 'Sin expiración') . "\n";
echo "   - Estado: " . ($isExpired ? '❌ Expirado' : '✅ Válido') . "\n\n";

// 7. Test de URL completa
echo "🌐 URL COMPLETA PARA DESCARGA:\n";
$baseUrl = 'http://localhost:8000'; // o la URL de tu app
$downloadUrl = "{$baseUrl}/api/transcriptions-temp/{$meeting->id}/download-ju";
echo "   {$downloadUrl}\n\n";

// 8. Resumen final
echo "📋 RESUMEN FINAL DEL TEST:\n";
echo "=========================\n";

$checks = [
    'Usuario BNI válido' => $isBniUser,
    'Archivos disponibles' => ($transcriptionExists || $audioExists),
    'Controlador funcionando' => isset($response) && $response !== null,
    'Respuesta es descarga' => isset($isDownload) && $isDownload,
    'Contenido sin encriptar' => isset($isValidJson) && $isValidJson,
    'No expirado' => !$isExpired
];

$allPassed = true;
foreach ($checks as $check => $passed) {
    echo ($passed ? "✅" : "❌") . " {$check}\n";
    if (!$passed) $allPassed = false;
}

echo "\n" . ($allPassed ? "🎉 ¡TEST COMPLETAMENTE EXITOSO!" : "⚠️  Algunos checks fallaron") . "\n";

if ($allPassed) {
    echo "\n🚀 INSTRUCCIONES PARA DESCARGA REAL:\n";
    echo "===================================\n";
    echo "1. Hacer login como CongresoBNI@gmail.com\n";
    echo "2. Hacer GET request a: {$downloadUrl}\n";
    echo "3. Incluir header: Authorization: Bearer {token}\n";
    echo "4. El archivo se descargará como 'reunion_temp_{$meeting->id}.ju'\n";
    echo "5. El contenido estará SIN ENCRIPTAR (JSON legible)\n\n";

    echo "📱 DESDE LA INTERFAZ WEB:\n";
    echo "========================\n";
    echo "1. Acceder a la reunión '{$meeting->title}'\n";
    echo "2. Buscar botón de descarga .ju\n";
    echo "3. Hacer clic -> descarga automática\n";
    echo "4. Archivo descargado sin encriptación\n";
}
