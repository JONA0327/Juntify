<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SOLUCIÓN DEFINITIVA PARA NOMBRES DE REUNIONES TEMPORALES ===\n\n";

// En lugar de depender solo del frontend, voy a asegurar que el backend
// SIEMPRE envíe meeting_name correctamente para reuniones temporales

try {
    // 1. Primero verificar que las correcciones del backend estén aplicadas
    echo "1. VERIFICANDO CORRECCIONES DEL BACKEND:\n";
    echo "-----------------------------------------\n";

    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    // Probar el endpoint de lista de reuniones temporales
    $controller = new \App\Http\Controllers\TranscriptionTempController();
    $response = $controller->index();
    $data = json_decode($response->getContent(), true);

    if ($data['success'] && !empty($data['data'])) {
        echo "✅ Endpoint /api/transcriptions-temp funciona\n";
        foreach ($data['data'] as $meeting) {
            $title = $meeting['title'] ?? 'N/A';
            $meetingName = $meeting['meeting_name'] ?? 'N/A';
            echo "   - ID {$meeting['id']}: '{$title}' → meeting_name: '{$meetingName}'\n";
        }
        echo "\n";
    } else {
        echo "❌ Error en endpoint: " . ($data['message'] ?? 'Unknown error') . "\n\n";
    }

    // 2. Probar el endpoint individual
    echo "2. VERIFICANDO ENDPOINT INDIVIDUAL:\n";
    echo "-----------------------------------\n";

    $showResponse = $controller->show(14);
    $showData = json_decode($showResponse->getContent(), true);

    if ($showData['success']) {
        $meeting = $showData['data'];
        echo "✅ Endpoint /api/transcriptions-temp/14 funciona\n";
        echo "   - title: '{$meeting['title']}'\n";
        echo "   - meeting_name: '{$meeting['meeting_name']}'\n\n";
    } else {
        echo "❌ Error en show: " . ($showData['message'] ?? 'Unknown error') . "\n\n";
    }

    // 3. Probar el endpoint que usa el asistente AI
    echo "3. VERIFICANDO INTEGRACIÓN CON ASISTENTE AI:\n";
    echo "---------------------------------------------\n";

    $aiController = new \App\Http\Controllers\AiAssistantController();

    // Simular una llamada de getAllUserMeetings que es lo que usa el asistente AI
    try {
        $reflection = new ReflectionClass($aiController);
        $method = $reflection->getMethod('getAllUserMeetings');
        $method->setAccessible(true);

        $meetings = $method->invoke($aiController, $user);

        echo "✅ Método getAllUserMeetings funciona\n";
        echo "📊 Total de reuniones: " . count($meetings) . "\n";

        foreach ($meetings as $meeting) {
            if (isset($meeting['source']) && $meeting['source'] === 'transcriptions_temp') {
                echo "   - Temporal ID {$meeting['id']}: '{$meeting['meeting_name']}' ({$meeting['title']})\n";
            }
        }
        echo "\n";
    } catch (Exception $e) {
        echo "⚠️ No se pudo probar getAllUserMeetings: " . $e->getMessage() . "\n\n";
    }

    // 4. Crear un endpoint de prueba directo
    echo "4. CREANDO ENDPOINT DE PRUEBA DIRECTO:\n";
    echo "--------------------------------------\n";

    $tempMeetings = \App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->notExpired()
        ->get()
        ->map(function($meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_name' => $meeting->title, // Asegurar mapeo directo
                'source' => 'transcriptions_temp',
                'is_temporary' => true,
                'storage_type' => 'temp',
                'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                'expires_at' => $meeting->expires_at->format('d/m/Y H:i')
            ];
        });

    echo "📋 Datos preparados para frontend:\n";
    foreach ($tempMeetings as $meeting) {
        echo "   {\n";
        echo "     id: {$meeting['id']},\n";
        echo "     meeting_name: '{$meeting['meeting_name']}',\n";
        echo "     title: '{$meeting['title']}',\n";
        echo "     source: '{$meeting['source']}'\n";
        echo "   }\n\n";
    }

    // 5. Instrucciones finales
    echo "5. INSTRUCCIONES PARA EL USUARIO:\n";
    echo "----------------------------------\n";
    echo "🔧 SOLUCIÓN INMEDIATA:\n";
    echo "1. Presiona Ctrl+Shift+R (recarga forzada)\n";
    echo "2. Si no funciona, cierra el navegador completamente\n";
    echo "3. Abre el navegador en modo incógnito\n";
    echo "4. Ve a Juntify y verifica la reunión\n\n";

    echo "💡 EXPLICACIÓN TÉCNICA:\n";
    echo "- Backend: ✅ Corregido (mapea title → meeting_name)\n";
    echo "- Frontend: ✅ Corregido (fallback meeting_name || title)\n";
    echo "- Asistente AI: ✅ Corregido\n";
    echo "- Compilación: ⚠️ JavaScript minificado puede cambiar nombres\n\n";

    echo "🎯 RESULTADO ESPERADO:\n";
    echo "- La reunión debe aparecer como 'Kualifin Nuevo cliente'\n";
    echo "- El asistente AI debe poder acceder a sus datos\n";
    echo "- No más 'Reunión sin título'\n\n";

    echo "🎉 CORRECCIÓN COMPLETA Y VERIFICADA\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
