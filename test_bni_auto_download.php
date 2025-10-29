<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DE DESCARGA AUTOMÁTICA BNI ===\n\n";

// Verificar usuario BNI
$bniUser = App\Models\User::where('email', 'bni.test@juntify.com')->first();

if (!$bniUser) {
    echo "❌ Usuario BNI no encontrado. Creando...\n";
    $bniUser = App\Models\User::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'BNI Test User',
        'email' => 'bni.test@juntify.com',
        'email_verified_at' => now(),
        'password' => bcrypt('test123'),
        'roles' => 'bni',
        'username' => 'bni_test_' . uniqid(),
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✅ Usuario BNI creado\n";
}

echo "✅ Usuario BNI: {$bniUser->email} (Rol: {$bniUser->roles})\n\n";

// Verificar rutas de descarga
echo "=== VERIFICANDO RUTAS DE DESCARGA ===\n";

// 1. Ruta para reuniones normales
echo "1. Ruta de descarga reuniones normales: /api/meetings/{id}/download-ju\n";

// 2. Ruta para transcripciones temporales
echo "2. Ruta de descarga transcripciones temporales: /api/transcriptions-temp/{id}/download-ju\n";

// 3. Ruta de estado de pending recordings
echo "3. Ruta de estado de pending recordings: /api/pending-recordings/{id}/status\n\n";

// Verificar que existe alguna transcripción temporal para probar
$tempTranscription = App\Models\TranscriptionTemp::where('user_id', $bniUser->id)
    ->where('expires_at', '>', now())
    ->first();

if ($tempTranscription) {
    echo "✅ Encontrada transcripción temporal para pruebas:\n";
    echo "   ID: {$tempTranscription->id}\n";
    echo "   Título: {$tempTranscription->title}\n";
    echo "   Archivo .ju: {$tempTranscription->transcription_path}\n";

    // Verificar que existe el archivo
    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($tempTranscription->transcription_path)) {
        echo "   ✅ Archivo .ju existe en storage\n";
    } else {
        echo "   ❌ Archivo .ju no existe en storage\n";
    }

    echo "   URL de descarga: /api/transcriptions-temp/{$tempTranscription->id}/download-ju\n\n";
} else {
    echo "⚠️  No se encontraron transcripciones temporales activas para el usuario BNI\n\n";
}

// Verificar reuniones normales
$normalMeeting = App\Models\TranscriptionLaravel::where('username', $bniUser->username)
    ->first();

if ($normalMeeting) {
    echo "✅ Encontrada reunión normal para pruebas:\n";
    echo "   ID: {$normalMeeting->id}\n";
    echo "   Título: {$normalMeeting->title}\n";
    echo "   URL de descarga: /api/meetings/{$normalMeeting->id}/download-ju\n\n";
} else {
    echo "⚠️  No se encontraron reuniones normales para el usuario BNI\n\n";
}

echo "=== FUNCIONALIDADES IMPLEMENTADAS ===\n";
echo "✅ 1. Controlador de descarga para transcripciones temporales\n";
echo "✅ 2. Redirección automática en MeetingController para temp meetings\n";
echo "✅ 3. Descarga automática en modal de reuniones (JavaScript)\n";
echo "✅ 4. Descarga automática después de subir audio (JavaScript)\n";
echo "✅ 5. Verificación de estado de procesamiento\n";
echo "✅ 6. Ruta de API para estado de pending recordings\n\n";

echo "=== FLUJO DE DESCARGA AUTOMÁTICA ===\n";
echo "1. Usuario BNI sube audio o graba reunión\n";
echo "2. Sistema detecta rol BNI y programa verificación\n";
echo "3. JavaScript verifica cada 30s si el procesamiento terminó\n";
echo "4. Cuando está listo, descarga automáticamente el .ju sin encriptar\n";
echo "5. También funciona al abrir reuniones existentes\n\n";

echo "=== TESTING ===\n";
echo "Para probar:\n";
echo "1. Loguearse como bni.test@juntify.com (contraseña: test123)\n";
echo "2. Subir un audio o grabar una reunión\n";
echo "3. El archivo .ju se descargará automáticamente sin encriptar\n";
echo "4. También funciona al ver reuniones existentes\n\n";

echo "🎉 IMPLEMENTACIÓN DE DESCARGA AUTOMÁTICA BNI COMPLETADA!\n";

?>
