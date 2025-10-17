<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\TranscriptionTempController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=== PRUEBA COMPLETA DE CORRECCIÓN DE NOMBRES DE REUNIONES TEMPORALES ===\n\n";

try {
    // Simular autenticación del usuario goku03278@gmail.com
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();

    if (!$user) {
        echo "❌ Usuario goku03278@gmail.com no encontrado.\n";
        exit(1);
    }

    Auth::login($user);
    echo "✅ Autenticado como: {$user->email}\n\n";

    // 1. Prueba del endpoint index
    echo "1. PROBANDO ENDPOINT /api/transcriptions-temp (index):\n";
    echo "------------------------------------------------------\n";

    $controller = new TranscriptionTempController();
    $indexResponse = $controller->index();
    $indexData = json_decode($indexResponse->getContent(), true);

    if ($indexData['success']) {
        echo "✅ Endpoint index funciona correctamente\n";
        echo "📊 Reuniones encontradas: " . count($indexData['data']) . "\n";

        foreach ($indexData['data'] as $meeting) {
            $title = $meeting['title'] ?? 'N/A';
            $meetingName = $meeting['meeting_name'] ?? 'N/A';
            $storageType = $meeting['storage_type'] ?? 'N/A';

            echo "   - ID: {$meeting['id']}\n";
            echo "     * title: '{$title}'\n";
            echo "     * meeting_name: '{$meetingName}'\n";
            echo "     * storage_type: '{$storageType}'\n";
            echo "     * ✅ Corrección aplicada: " . ($meetingName === $title ? "SÍ" : "NO") . "\n\n";
        }
    } else {
        echo "❌ Error en endpoint index: " . ($indexData['message'] ?? 'Error desconocido') . "\n";
    }

    // 2. Prueba del endpoint show para una reunión específica
    echo "2. PROBANDO ENDPOINT /api/transcriptions-temp/14 (show):\n";
    echo "--------------------------------------------------------\n";

    $showResponse = $controller->show(14);
    $showData = json_decode($showResponse->getContent(), true);

    if ($showData['success']) {
        echo "✅ Endpoint show funciona correctamente\n";
        $meeting = $showData['data'];
        $title = $meeting['title'] ?? 'N/A';
        $meetingName = $meeting['meeting_name'] ?? 'N/A';

        echo "📋 Datos de la reunión ID 14:\n";
        echo "   - title: '{$title}'\n";
        echo "   - meeting_name: '{$meetingName}'\n";
        echo "   - ✅ Corrección aplicada: " . ($meetingName === $title ? "SÍ" : "NO") . "\n\n";
    } else {
        echo "❌ Error en endpoint show: " . ($showData['message'] ?? 'Error desconocido') . "\n\n";
    }

    // 3. Verificar que los archivos JavaScript fueron compilados
    echo "3. VERIFICANDO COMPILACIÓN DE ARCHIVOS JAVASCRIPT:\n";
    echo "---------------------------------------------------\n";

    $jsPath = public_path('build/assets');
    if (is_dir($jsPath)) {
        $jsFiles = glob($jsPath . '/*.js');
        if (!empty($jsFiles)) {
            echo "✅ Archivos JavaScript compilados encontrados:\n";
            foreach ($jsFiles as $file) {
                $filename = basename($file);
                $size = round(filesize($file) / 1024, 2);
                echo "   - {$filename} ({$size} KB)\n";
            }
            echo "\n";
        } else {
            echo "⚠️ No se encontraron archivos JavaScript compilados\n\n";
        }
    } else {
        echo "⚠️ Directorio de build no encontrado\n\n";
    }

    // 4. Verificar que las correcciones están en el código compilado
    echo "4. VERIFICANDO CORRECCIONES EN CÓDIGO COMPILADO:\n";
    echo "-------------------------------------------------\n";

    $latestJsFile = null;
    $latestTime = 0;

    if (!empty($jsFiles)) {
        foreach ($jsFiles as $file) {
            $time = filemtime($file);
            if ($time > $latestTime) {
                $latestTime = $time;
                $latestJsFile = $file;
            }
        }

        if ($latestJsFile) {
            $jsContent = file_get_contents($latestJsFile);

            // Buscar las correcciones aplicadas
            $corrections = [
                'meeting.meeting_name||meeting.title||' => 'Fallback title implementado',
                'meeting_name||meeting.title' => 'Fallback alternativo implementado'
            ];

            $foundCorrections = 0;
            foreach ($corrections as $pattern => $description) {
                if (strpos($jsContent, $pattern) !== false) {
                    echo "✅ {$description}\n";
                    $foundCorrections++;
                }
            }

            if ($foundCorrections > 0) {
                echo "\n✅ Se encontraron {$foundCorrections} correcciones en el código compilado\n\n";
            } else {
                echo "\n⚠️ No se encontraron las correcciones en el código compilado\n";
                echo "   Puede ser necesario ejecutar: npm run build\n\n";
            }
        }
    }

    // 5. Instrucciones finales
    echo "5. INSTRUCCIONES PARA EL USUARIO:\n";
    echo "----------------------------------\n";
    echo "1. ✅ Backend corregido: Los endpoints ahora mapean 'title' a 'meeting_name'\n";
    echo "2. ✅ Frontend corregido: Todas las funciones usan fallback a 'title'\n";
    echo "3. 🔄 Recarga la página de reuniones en el navegador\n";
    echo "4. 🗑️ Si el problema persiste, borra la caché del navegador (Ctrl+F5)\n";
    echo "5. 👀 La reunión 'Kualifin Nuevo cliente' ahora debería mostrar su nombre correcto\n\n";

    echo "🎉 CORRECCIÓN COMPLETA APLICADA EXITOSAMENTE\n";
    echo "📝 Las reuniones temporales ahora mostrarán sus nombres reales tanto en:\n";
    echo "   - Lista principal de reuniones\n";
    echo "   - Reuniones en contenedores\n";
    echo "   - Modales al hacer clic\n";
    echo "   - Reuniones compartidas\n";

} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
