<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== INVESTIGANDO CONTEXTO DE REUNIONES TEMPORALES EN AI ===\n\n";

try {
    // Autenticar usuario
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    echo "1. BUSCANDO SESIONES DE CHAT EXISTENTES:\n";
    echo "=========================================\n";

    $sessions = \App\Models\AiChatSession::where('username', $user->email)
        ->orderByDesc('last_activity')
        ->get();

    echo "✅ Total de sesiones encontradas: " . count($sessions) . "\n\n";

    foreach ($sessions as $session) {
        echo "Sesión ID {$session->id}:\n";
        echo "   - Título: '{$session->title}'\n";
        echo "   - Tipo de contexto: {$session->context_type}\n";
        echo "   - ID de contexto: {$session->context_id}\n";
        echo "   - Datos de contexto: " . json_encode($session->context_data) . "\n";
        echo "   - Última actividad: {$session->last_activity}\n";
        echo "   - Activa: " . ($session->is_active ? 'SÍ' : 'NO') . "\n\n";

        // Si tiene context_id, verificar qué tipo de reunión es
        if ($session->context_id) {
            // Buscar en reuniones normales
            $normalMeeting = \App\Models\TranscriptionLaravel::find($session->context_id);
            if ($normalMeeting) {
                echo "   → Es reunión normal: '{$normalMeeting->meeting_name}'\n";
            } else {
                // Buscar en reuniones temporales
                $tempMeeting = \App\Models\TranscriptionTemp::find($session->context_id);
                if ($tempMeeting) {
                    echo "   → Es reunión TEMPORAL: '{$tempMeeting->title}'\n";
                    echo "   → ⚠️ PROBLEMA: El sistema no detecta que es temporal!\n";
                } else {
                    echo "   → Reunión no encontrada (puede estar eliminada)\n";
                }
            }
        }
        echo "\n";
    }

    echo "2. SIMULANDO CONTEXTO DE REUNIÓN TEMPORAL:\n";
    echo "===========================================\n";

    // Buscar la reunión temporal Kualifin
    $tempMeeting = \App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->where('title', 'Kualifin Nuevo cliente')
        ->first();

    if ($tempMeeting) {
        echo "✅ Reunión temporal encontrada: ID {$tempMeeting->id}\n";

        // Crear o encontrar sesión para esta reunión
        $session = \App\Models\AiChatSession::where('username', $user->email)
            ->where('context_id', $tempMeeting->id)
            ->first();

        if (!$session) {
            echo "📝 Creando nueva sesión de prueba...\n";
            $session = \App\Models\AiChatSession::create([
                'username' => $user->email,
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
            echo "✅ Sesión existente encontrada: ID {$session->id}\n";
            // Actualizar context_data si no tiene source
            if (!isset($session->context_data['source'])) {
                $session->update([
                    'context_data' => array_merge($session->context_data ?? [], [
                        'source' => 'transcriptions_temp',
                        'meeting_id' => $tempMeeting->id,
                        'is_temporary' => true
                    ])
                ]);
                echo "✅ Datos de contexto actualizados\n";
            }
        }

        echo "\n3. PROBANDO buildMeetingContextFragments CON REUNIÓN TEMPORAL:\n";
        echo "==============================================================\n";

        // Crear instancia del controlador
        $googleDriveService = app(\App\Services\GoogleDriveService::class);
        $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
        $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass($aiController);
        $method = $reflection->getMethod('buildMeetingContextFragments');
        $method->setAccessible(true);

        try {
            $fragments = $method->invoke($aiController, $session, 'información sobre créditos');

            echo "✅ Fragmentos generados: " . count($fragments) . "\n";

            if (empty($fragments)) {
                echo "❌ PROBLEMA: No se generaron fragmentos para la reunión temporal\n";
                echo "   Esto explica por qué el asistente AI no puede acceder a la información\n";
            } else {
                echo "✅ Fragmentos obtenidos exitosamente:\n";
                foreach (array_slice($fragments, 0, 3) as $i => $fragment) {
                    echo "   " . ($i + 1) . ". " . substr($fragment['text'], 0, 100) . "...\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Error al generar fragmentos: " . $e->getMessage() . "\n";
        }

        echo "\n=== DIAGNÓSTICO ===\n";
        echo "PROBLEMA IDENTIFICADO: El método buildMeetingContextFragments\n";
        echo "solo busca en TranscriptionLaravel, no en TranscriptionTemp\n\n";

        echo "SOLUCIÓN NECESARIA: Modificar getMeetingIfAccessible\n";
        echo "para que detecte el source en context_data y busque en la tabla correcta\n";

    } else {
        echo "❌ No se encontró la reunión temporal\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
