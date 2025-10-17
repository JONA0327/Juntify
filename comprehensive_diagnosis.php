<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIAGNÓSTICO COMPLETO DEL PROBLEMA DE NOMBRES DE REUNIONES ===\n\n";

try {
    // 1. Verificar datos de la reunión 14 específicamente
    echo "1. VERIFICANDO REUNIÓN TEMPORAL ID 14:\n";
    echo "-------------------------------------\n";

    $meeting = \App\Models\TranscriptionTemp::find(14);
    if ($meeting) {
        echo "✅ Reunión encontrada:\n";
        echo "   - ID: {$meeting->id}\n";
        echo "   - Title: '{$meeting->title}'\n";
        echo "   - User ID: {$meeting->user_id}\n";
        echo "   - Created: {$meeting->created_at}\n";
        echo "   - Expires: {$meeting->expires_at}\n\n";
    } else {
        echo "❌ Reunión 14 no encontrada\n\n";
        exit(1);
    }

    // 2. Verificar usuario y autenticación
    echo "2. VERIFICANDO USUARIO GOKU03278@GMAIL.COM:\n";
    echo "--------------------------------------------\n";

    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    if ($user) {
        echo "✅ Usuario encontrado:\n";
        echo "   - ID: {$user->id}\n";
        echo "   - Email: {$user->email}\n";
        echo "   - Rol: {$user->roles}\n";
        echo "   - Plan expira: " . ($user->plan_expires_at ?? 'Sin límite') . "\n\n";

        // Verificar si la reunión pertenece al usuario
        if ($meeting->user_id === $user->id) {
            echo "✅ La reunión pertenece al usuario\n\n";
        } else {
            echo "❌ La reunión NO pertenece al usuario\n";
            echo "   - Reunión user_id: {$meeting->user_id}\n";
            echo "   - Usuario ID: {$user->id}\n\n";
        }
    } else {
        echo "❌ Usuario no encontrado\n\n";
    }

    // 3. Simular endpoint de lista de reuniones temporales
    echo "3. SIMULANDO ENDPOINT /api/transcriptions-temp:\n";
    echo "-----------------------------------------------\n";

    \Auth::login($user);

    $tempMeetings = \App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->notExpired()
        ->orderBy('created_at', 'desc')
        ->get();

    echo "📊 Reuniones temporales encontradas: " . $tempMeetings->count() . "\n";

    foreach ($tempMeetings as $temp) {
        echo "   - ID: {$temp->id}\n";
        echo "     * title: '{$temp->title}'\n";
        echo "     * storage_type: temp\n";
        echo "     * is_temporary: true\n";
        echo "     * expires_at: {$temp->expires_at}\n\n";
    }

    // 4. Simular respuesta del endpoint con las correcciones
    echo "4. SIMULANDO RESPUESTA CORREGIDA:\n";
    echo "---------------------------------\n";

    $responseData = $tempMeetings->map(function($temp) {
        return [
            'id' => $temp->id,
            'title' => $temp->title,
            'meeting_name' => $temp->title, // Corrección aplicada
            'storage_type' => 'temp',
            'is_temporary' => true,
            'created_at' => $temp->created_at->format('d/m/Y H:i'),
            'expires_at' => $temp->expires_at
        ];
    });

    foreach ($responseData as $meeting) {
        echo "✅ Reunión lista para frontend:\n";
        echo "   - ID: {$meeting['id']}\n";
        echo "   - meeting_name: '{$meeting['meeting_name']}'\n";
        echo "   - title: '{$meeting['title']}'\n";
        echo "   - Fallback JS debería mostrar: '{$meeting['meeting_name']}'\n\n";
    }

    // 5. Verificar problema del asistente AI
    echo "5. VERIFICANDO INTEGRACIÓN CON ASISTENTE AI:\n";
    echo "---------------------------------------------\n";

    // Simular cómo el asistente AI ve las reuniones temporales
    $aiMeetings = \App\Models\TranscriptionTemp::where('user_id', $user->id)
        ->notExpired()
        ->orderByDesc('created_at')
        ->get()
        ->map(function ($meeting) {
            return [
                'id' => $meeting->id,
                'meeting_name' => $meeting->title, // Corrección aplicada
                'title' => $meeting->title . ' (Temporal)',
                'source' => 'transcriptions_temp',
                'is_legacy' => false,
                'is_shared' => false,
                'is_temporary' => true,
                'has_transcription' => !empty($meeting->transcription_path),
                'has_audio' => !empty($meeting->audio_path),
            ];
        });

    echo "📋 Reuniones disponibles para AI:\n";
    foreach ($aiMeetings as $ai) {
        echo "   - ID: {$ai['id']}\n";
        echo "     * meeting_name: '{$ai['meeting_name']}'\n";
        echo "     * title: '{$ai['title']}'\n";
        echo "     * source: {$ai['source']}\n";
        echo "     * has_transcription: " . ($ai['has_transcription'] ? 'true' : 'false') . "\n\n";
    }

    // 6. Verificar archivos JavaScript compilados
    echo "6. VERIFICANDO ARCHIVOS JAVASCRIPT:\n";
    echo "-----------------------------------\n";

    $jsPath = public_path('build/assets');
    if (is_dir($jsPath)) {
        $jsFiles = glob($jsPath . '/reuniones_v2-*.js');
        if (!empty($jsFiles)) {
            $latestJs = array_reduce($jsFiles, function($latest, $file) {
                return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
            });

            echo "✅ Archivo JavaScript principal: " . basename($latestJs) . "\n";
            echo "   - Última modificación: " . date('Y-m-d H:i:s', filemtime($latestJs)) . "\n";
            echo "   - Tamaño: " . round(filesize($latestJs) / 1024, 2) . " KB\n\n";

            // Verificar si contiene las correcciones
            $content = file_get_contents($latestJs);
            if (strpos($content, 'meeting_name||meeting.title') !== false) {
                echo "✅ Correcciones encontradas en JavaScript compilado\n\n";
            } else {
                echo "⚠️ No se encontraron las correcciones en JavaScript\n";
                echo "   Ejecutar: npm run build\n\n";
            }
        } else {
            echo "❌ No se encontraron archivos JavaScript compilados\n\n";
        }
    }

    // 7. Instrucciones finales
    echo "7. INSTRUCCIONES PARA RESOLVER EL PROBLEMA:\n";
    echo "--------------------------------------------\n";
    echo "1. 🔄 Cerrar completamente el navegador\n";
    echo "2. 🗑️ Borrar caché del navegador (Ctrl+Shift+Delete)\n";
    echo "3. 🌐 Abrir navegador en modo incógnito\n";
    echo "4. 🔗 Ir a Juntify y verificar que aparezca 'Kualifin Nuevo cliente'\n";
    echo "5. 🤖 Probar asistente AI con la reunión #14\n\n";

    echo "🎯 RESULTADO ESPERADO:\n";
    echo "- Lista: 'Kualifin Nuevo cliente' (no 'Reunión sin título')\n";
    echo "- AI: Debe reconocer la reunión y mostrar su contenido\n\n";

    echo "🎉 DIAGNÓSTICO COMPLETO\n";

} catch (Exception $e) {
    echo "❌ Error durante el diagnóstico: " . $e->getMessage() . "\n";
    exit(1);
}
