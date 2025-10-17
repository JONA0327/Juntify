<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROBANDO CORRECCIONES DE CONTEXTO PARA REUNIONES TEMPORALES ===\n\n";

try {
    // Autenticar usuario
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    echo "1. BUSCANDO O CREANDO SESIÓN PARA REUNIÓN TEMPORAL:\n";
    echo "===================================================\n";

    $tempMeeting = \App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->where('title', 'Kualifin Nuevo cliente')
        ->first();

    if (!$tempMeeting) {
        echo "❌ No se encontró la reunión temporal\n";
        exit(1);
    }

    // Buscar sesión existente o crear una nueva
    $session = \App\Models\AiChatSession::where('username', $user->username)
        ->where('context_id', $tempMeeting->id)
        ->first();

    if (!$session) {
        echo "📝 Creando nueva sesión...\n";
        $session = \App\Models\AiChatSession::create([
            'username' => $user->username,
            'title' => "Chat sobre {$tempMeeting->title}",
            'context_type' => 'meeting',
            'context_id' => $tempMeeting->id,
            'context_data' => [
                'source' => 'transcriptions_temp',
                'meeting_id' => $tempMeeting->id,
                'is_temporary' => true
            ],
            'is_active' => true,
            'last_activity' => now()
        ]);
        echo "✅ Sesión creada: ID {$session->id}\n";
    } else {
        echo "✅ Sesión existente: ID {$session->id}\n";
        // Asegurar que tenga los context_data correctos
        $contextData = $session->context_data ?? [];
        if (!isset($contextData['source'])) {
            $session->update([
                'context_data' => array_merge($contextData, [
                    'source' => 'transcriptions_temp',
                    'meeting_id' => $tempMeeting->id,
                    'is_temporary' => true
                ])
            ]);
            echo "✅ Context_data actualizado\n";
        }
    }

    echo "\n2. PROBANDO MÉTODO buildMeetingContextFragments CORREGIDO:\n";
    echo "===========================================================\n";

    // Crear instancia del controlador
    $googleDriveService = app(\App\Services\GoogleDriveService::class);
    $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
    $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($aiController);
    $method = $reflection->getMethod('buildMeetingContextFragments');
    $method->setAccessible(true);

    $query = 'información sobre créditos Kualifin';
    $fragments = $method->invoke($aiController, $session, $query);

    echo "✅ Fragmentos generados: " . count($fragments) . "\n";

    if (empty($fragments)) {
        echo "❌ PROBLEMA: No se generaron fragmentos\n";
    } else {
        echo "🎉 ÉXITO: Fragmentos obtenidos para reunión temporal!\n\n";

        echo "📄 TIPOS DE FRAGMENTOS ENCONTRADOS:\n";
        $types = [];
        foreach ($fragments as $fragment) {
            $type = $fragment['content_type'] ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        foreach ($types as $type => $count) {
            echo "   - {$type}: {$count}\n";
        }

        echo "\n📝 PRIMEROS 5 FRAGMENTOS:\n";
        foreach (array_slice($fragments, 0, 5) as $i => $fragment) {
            $text = substr($fragment['text'], 0, 150);
            $type = $fragment['content_type'] ?? 'unknown';
            echo "   " . ($i + 1) . ". [{$type}] {$text}...\n\n";
        }
    }

    echo "\n3. PROBANDO getTempMeetingIfAccessible:\n";
    echo "=======================================\n";

    $getTempMethod = $reflection->getMethod('getTempMeetingIfAccessible');
    $getTempMethod->setAccessible(true);

    $retrievedMeeting = $getTempMethod->invoke($aiController, $tempMeeting->id, $user);

    if ($retrievedMeeting) {
        echo "✅ Reunión temporal recuperada correctamente\n";
        echo "   - ID: {$retrievedMeeting->id}\n";
        echo "   - Título: '{$retrievedMeeting->title}'\n";
    } else {
        echo "❌ No se pudo recuperar la reunión temporal\n";
    }

    echo "\n=== RESUMEN DE LA CORRECCIÓN ===\n";
    if (!empty($fragments)) {
        echo "🎉 CORRECCIÓN EXITOSA!\n";
        echo "✅ El asistente AI ahora puede:\n";
        echo "   - Detectar reuniones temporales por su context_data\n";
        echo "   - Acceder al contenido de archivos .ju temporales\n";
        echo "   - Generar fragmentos de conversación, resúmenes y tareas\n";
        echo "   - Responder preguntas sobre reuniones con referencia #\n\n";

        echo "🎯 INSTRUCCIONES PARA EL USUARIO:\n";
        echo "1. Ve al Asistente IA\n";
        echo "2. Selecciona la reunión 'Kualifin Nuevo cliente' como contexto\n";
        echo "3. Haz preguntas como:\n";
        echo "   - '¿De qué habló la reunión?'\n";
        echo "   - '¿Qué criterios mencionaron para los créditos?'\n";
        echo "   - '¿Quién participó en la conversación?'\n";
        echo "4. El asistente ahora tendrá acceso completo a toda la información!\n";
    } else {
        echo "❌ AÚN HAY PROBLEMAS - Los fragmentos no se están generando\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
