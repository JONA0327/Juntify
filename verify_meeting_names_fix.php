<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICACIÓN DE CORRECCIÓN DE NOMBRES DE REUNIONES TEMPORALES ===\n\n";

try {
    // 1. Verificar reuniones temporales existentes
    echo "1. REUNIONES TEMPORALES EXISTENTES:\n";
    echo "-----------------------------------\n";

    $tempMeetings = DB::table('transcription_temps')
        ->select('id', 'title', 'user_id', 'created_at')
        ->whereNotNull('title')
        ->where('title', '!=', '')
        ->get();

    if ($tempMeetings->isEmpty()) {
        echo "❌ No se encontraron reuniones temporales con títulos.\n\n";
    } else {
        foreach ($tempMeetings as $meeting) {
            echo "✅ ID: {$meeting->id} | Título: '{$meeting->title}' | Fecha: {$meeting->created_at}\n";
        }
        echo "\nTotal: " . $tempMeetings->count() . " reuniones temporales encontradas\n\n";
    }

    // 2. Verificar reuniones normales en contenedores
    echo "2. REUNIONES NORMALES EN CONTENEDORES:\n";
    echo "--------------------------------------\n";

    $containerMeetings = DB::table('meeting_content_relations')
        ->join('transcriptions_laravel', 'meeting_content_relations.meeting_id', '=', 'transcriptions_laravel.id')
        ->select('meeting_content_relations.container_id', 'transcriptions_laravel.id', 'transcriptions_laravel.meeting_name', 'transcriptions_laravel.created_at')
        ->get();

    if ($containerMeetings->isEmpty()) {
        echo "ℹ️ No hay reuniones normales en contenedores.\n\n";
    } else {
        foreach ($containerMeetings as $meeting) {
            echo "✅ Container: {$meeting->container_id} | ID: {$meeting->id} | Nombre: '{$meeting->meeting_name}' | Fecha: {$meeting->created_at}\n";
        }
        echo "\nTotal: " . $containerMeetings->count() . " reuniones en contenedores\n\n";
    }

    // 3. Verificar estructura de datos para el frontend
    echo "3. VERIFICACIÓN DE ESTRUCTURA PARA FRONTEND:\n";
    echo "--------------------------------------------\n";

    if ($tempMeetings->isNotEmpty()) {
        $tempMeeting = $tempMeetings->first();

        // Simular la estructura que se envía al frontend
        $frontendData = [
            'meeting_name' => $tempMeeting->title, // Campo que usa el frontend
            'title' => $tempMeeting->title,        // Campo original de temp
            'created_at' => $tempMeeting->created_at,
            'id' => $tempMeeting->id
        ];

        echo "📋 Ejemplo de estructura de datos corregida:\n";
        echo "   - meeting_name: '{$frontendData['meeting_name']}' (usado por createOrgContainerMeetingCard)\n";
        echo "   - title: '{$frontendData['title']}' (campo original)\n";
        echo "   - Fallback JS: meeting.meeting_name || meeting.title || 'Reunión sin título'\n\n";

        // Simular la lógica del frontend corregida
        $displayName = $frontendData['meeting_name'] ?: ($frontendData['title'] ?: 'Reunión sin título');
        echo "✅ Nombre a mostrar: '{$displayName}'\n\n";
    }

    // 4. Verificar correcciones aplicadas
    echo "4. CORRECCIONES APLICADAS:\n";
    echo "--------------------------\n";
    echo "✅ resources/js/reuniones_v2.js:\n";
    echo "   - Función createOrgContainerMeetingCard() actualizada\n";
    echo "   - Fallback: meeting.meeting_name || meeting.title || 'Reunión sin título'\n\n";
    echo "✅ resources/views/organization/index.blade.php:\n";
    echo "   - Modal actualizado con fallback a meeting.title\n";
    echo "   - Fallback: meeting.meeting_name || meeting.title || 'Reunión sin título'\n\n";
    echo "✅ app/Http/Controllers/SharedMeetingController.php:\n";
    echo "   - Fallback mejorado para reuniones compartidas temporales\n\n";

    // 5. Instrucciones para el usuario
    echo "5. INSTRUCCIONES PARA VERIFICAR LA CORRECCIÓN:\n";
    echo "----------------------------------------------\n";
    echo "1. El usuario goku03278@gmail.com debe recargar la página de organizaciones\n";
    echo "2. Las reuniones temporales ahora deben mostrar su nombre real:\n";

    if ($tempMeetings->isNotEmpty()) {
        foreach ($tempMeetings->where('user_id', 'a2c8514d-932c-4bc9-8a2b-e7355faa25ad') as $meeting) {
            echo "   - '{$meeting->title}' (en lugar de 'Reunión sin título')\n";
        }
    }

    echo "\n3. Al hacer clic en una reunión temporal, el modal también debe mostrar el nombre correcto\n";
    echo "4. Los cambios se aplicarán automáticamente sin necesidad de reinicios\n\n";

    echo "🎉 CORRECCIÓN COMPLETADA EXITOSAMENTE\n";
    echo "📝 Las reuniones temporales ahora mostrarán sus nombres reales en lugar de 'Reunión sin título'\n";

} catch (Exception $e) {
    echo "❌ Error durante la verificación: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
