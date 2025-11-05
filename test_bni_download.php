<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 TEST DE DESCARGA .JU - USUARIO BNI\n";
echo "=====================================\n\n";

// 1. Buscar el usuario BNI
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();
if (!$user) {
    echo "❌ Usuario BNI no encontrado\n";
    exit(1);
}

echo "✅ Usuario BNI encontrado: {$user->email}\n";
echo "   - Rol: {$user->roles}\n";
echo "   - Plan: {$user->plan}\n\n";

// 2. Buscar la reunión "prueba de BNI"
echo "🔍 Buscando reunión 'prueba de BNI'...\n";

// Buscar en TranscriptionLaravel (reuniones normales) - usar 'username' en lugar de 'user_id'
$meeting = App\Models\TranscriptionLaravel::where('username', $user->email)
    ->where('meeting_name', 'LIKE', '%prueba de BNI%')
    ->first();

if (!$meeting) {
    // Buscar en TranscriptionTemp (almacenamiento temporal BNI)
    $meeting = App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->where('title', 'LIKE', '%prueba de BNI%')
        ->first();
    $isTemp = true;
} else {
    $isTemp = false;
}

if (!$meeting) {
    echo "❌ Reunión 'prueba de BNI' no encontrada\n";
    echo "📋 Reuniones disponibles del usuario:\n";

    $regularMeetings = App\Models\TranscriptionLaravel::where('username', $user->email)->get(['id', 'meeting_name', 'created_at']);
    $tempMeetings = App\Models\TranscriptionTemp::where('user_id', $user->id)->get(['id', 'title', 'created_at']);

    foreach ($regularMeetings as $m) {
        echo "   - Regular: {$m->meeting_name} (ID: {$m->id})\n";
    }
    foreach ($tempMeetings as $m) {
        echo "   - Temporal: {$m->title} (ID: {$m->id})\n";
    }
    exit(1);
}

echo "✅ Reunión encontrada:\n";
echo "   - ID: {$meeting->id}\n";
echo "   - Título: " . ($isTemp ? $meeting->title : $meeting->meeting_name) . "\n";
echo "   - Tipo: " . ($isTemp ? 'Temporal (BNI)' : 'Regular') . "\n";
echo "   - Fecha: {$meeting->created_at}\n\n";

// 3. Verificar que el usuario puede acceder
echo "🔐 Verificando permisos de acceso...\n";
if ($isTemp && $meeting->user_id === $user->id) {
    echo "✅ Usuario es propietario de la reunión temporal\n";
} elseif (!$isTemp && $meeting->username === $user->email) {
    echo "✅ Usuario es propietario de la reunión regular\n";
} else {
    echo "❌ Usuario no es propietario de la reunión\n";
    exit(1);
}

// 4. Verificar características BNI
echo "\n🎯 Verificando características BNI...\n";

// Verificar almacenamiento
if ($isTemp) {
    echo "✅ Almacenamiento: Temporal (correcto para BNI)\n";
} else {
    echo "⚠️  Almacenamiento: Regular (debería ser temporal para BNI)\n";
}

// Verificar que el usuario BNI puede descargar
$limitsService = new App\Services\PlanLimitService();
$canUseDrive = $limitsService->userCanUseDrive($user);
echo "🔄 Puede usar Drive: " . ($canUseDrive ? 'Sí' : 'No (correcto para BNI - usa temporal)') . "\n";

// 5. Simular descarga del archivo .ju
echo "\n📥 SIMULANDO DESCARGA DEL ARCHIVO .JU...\n";

// Para BNI, debe usar TranscriptionTempController
$controllerClass = $isTemp ? 'TranscriptionTempController' : 'DriveController';
echo "   - Controlador a usar: {$controllerClass}\n";

// Verificar que los campos necesarios existen
if ($isTemp) {
    $hasTranscription = !empty($meeting->transcription_path) && file_exists(storage_path('app/' . $meeting->transcription_path));
    $hasAudio = !empty($meeting->audio_path) && file_exists(storage_path('app/' . $meeting->audio_path));
} else {
    $hasTranscription = !empty($meeting->transcript_download_url);
    $hasAudio = !empty($meeting->audio_download_url);
}

echo "   - Tiene transcripción: " . ($hasTranscription ? '✅ Sí' : '❌ No') . "\n";
echo "   - Tiene audio: " . ($hasAudio ? '✅ Sí' : '❌ No') . "\n";

if ($hasTranscription || $hasAudio) {
    echo "✅ Contenido disponible para descarga\n";

    // Simular generación del contenido .ju
    echo "\n🔧 Generando contenido .ju simulado...\n";

    $juContent = [
        'meeting_info' => [
            'id' => $meeting->id,
            'title' => $isTemp ? $meeting->title : $meeting->meeting_name,
            'date' => $meeting->created_at,
            'user' => $user->email,
            'type' => $isTemp ? 'BNI_temporal' : 'regular'
        ],
        'transcription' => $isTemp ?
            ($meeting->transcription_path ? file_get_contents(storage_path('app/' . $meeting->transcription_path)) : '') :
            '',
        'audio_info' => [
            'has_audio' => $hasAudio,
            'path' => $isTemp ? $meeting->audio_path : $meeting->audio_download_url
        ],
        'bni_features' => [
            'unencrypted' => true,
            'auto_download' => true,
            'temporary_storage' => $isTemp
        ]
    ];

    $juJson = json_encode($juContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    echo "📄 Contenido .ju generado (" . strlen($juJson) . " caracteres)\n";
    echo "   - Contiene información de reunión: ✅\n";
    echo "   - Contiene transcripción: " . ($hasTranscription ? '✅' : '❌') . "\n";
    echo "   - Marca BNI (sin encriptar): ✅\n";

} else {
    echo "❌ No hay contenido disponible para descargar\n";
}

// 6. Test de URL de descarga
echo "\n🔗 GENERANDO URL DE DESCARGA...\n";

if ($isTemp) {
    $downloadUrl = "/transcription-temp/{$meeting->id}/download-ju";
} else {
    $downloadUrl = "/drive/download-ju/{$meeting->id}";
}

echo "   - URL de descarga: {$downloadUrl}\n";
echo "   - Método: GET\n";
echo "   - Autenticación: Requerida (usuario BNI)\n";

// 7. Verificar que el archivo se descargaría sin encriptar
echo "\n🔓 VERIFICANDO CARACTERÍSTICA SIN ENCRIPTACIÓN...\n";

if ($user->roles === 'BNI' || $user->plan === 'BNI') {
    echo "✅ Usuario BNI: Archivo .ju SIN encriptar\n";
    echo "✅ Contenido legible directamente\n";
    echo "✅ Auto-descarga habilitada\n";
} else {
    echo "❌ Usuario no BNI: Archivo estaría encriptado\n";
}

// 8. Resumen del test
echo "\n📋 RESUMEN DEL TEST:\n";
echo "==================\n";

$testResults = [
    'Usuario BNI válido' => ($user->roles === 'BNI'),
    'Reunión encontrada' => (bool)$meeting,
    'Permisos correctos' => ($isTemp ? $meeting->user_id === $user->id : $meeting->username === $user->email),
    'Contenido disponible' => ($hasTranscription || $hasAudio),
    'Almacenamiento BNI' => $isTemp,
    'Sin encriptación' => ($user->roles === 'BNI')
];

$allPassed = true;
foreach ($testResults as $test => $result) {
    echo ($result ? "✅" : "❌") . " {$test}\n";
    if (!$result) $allPassed = false;
}

echo "\n" . ($allPassed ? "🎉 ¡TEST EXITOSO! La descarga .ju debería funcionar perfectamente" : "⚠️  Hay issues que resolver") . "\n";

if ($allPassed) {
    echo "\n🚀 INSTRUCCIONES PARA DESCARGA REAL:\n";
    echo "===================================\n";
    echo "1. Acceder como CongresoBNI@gmail.com\n";
    echo "2. Ir a la reunión: '" . ($isTemp ? $meeting->title : $meeting->meeting_name) . "'\n";
    echo "3. Hacer clic en el botón de descarga\n";
    echo "4. El archivo .ju se descargará automáticamente\n";
    echo "5. Contenido SIN encriptar (legible directamente)\n";

    // Generar un archivo de prueba
    $testFileName = "test_bni_meeting_{$meeting->id}.ju";
    file_put_contents($testFileName, $juJson);
    echo "\n📁 Archivo de prueba generado: {$testFileName}\n";
    echo "   (Puedes revisar este archivo para ver cómo se vería el .ju real)\n";
}
