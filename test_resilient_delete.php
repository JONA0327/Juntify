<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba del método deleteFolderResilient mejorado ===\n\n";

try {
    // 1. Obtener usuario
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No hay usuario con token\n";
        exit(1);
    }
    echo "✅ Usuario: {$user->email}\n";

    // 2. Crear carpeta de prueba
    $sa = app(GoogleServiceAccount::class);
    $testFolderId = $sa->createFolder('TEST_RESILIENT_DELETE_' . time());
    echo "✅ Carpeta de prueba creada: {$testFolderId}\n";

    // 3. Probar el método deleteFolderResilient
    echo "\n🔄 Probando deleteFolderResilient...\n";
    $driveService = app(GoogleDriveService::class);

    $success = $driveService->deleteFolderResilient($testFolderId, $user->email);

    if ($success) {
        echo "✅ ÉXITO: deleteFolderResilient retornó true\n";

        // Verificar que realmente se eliminó
        try {
            $sa->getFileInfo($testFolderId);
            echo "❌ PROBLEMA: La carpeta AÚN EXISTE después de deleteFolderResilient\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'File not found') !== false ||
                strpos($e->getMessage(), '404') !== false) {
                echo "✅ CONFIRMADO: Carpeta eliminada correctamente del Drive\n";
            } else {
                echo "⚠️  Error verificando eliminación: {$e->getMessage()}\n";
            }
        }
    } else {
        echo "❌ FALLO: deleteFolderResilient retornó false\n";

        // Verificar si la carpeta aún existe
        try {
            $fileInfo = $sa->getFileInfo($testFolderId);
            echo "❌ CONFIRMADO: La carpeta AÚN EXISTE: {$fileInfo->getName()}\n";

            // Intentar eliminar manualmente
            echo "🔄 Intentando eliminación manual...\n";
            $sa->deleteFile($testFolderId);
            echo "✅ Eliminación manual exitosa\n";

        } catch (\Exception $e) {
            echo "Error al verificar/eliminar manualmente: {$e->getMessage()}\n";
        }
    }

    // 4. Verificar logs recientes
    echo "\n--- Logs recientes ---\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -15);

        $foundRelevant = false;
        foreach ($recentLines as $line) {
            if (strpos($line, 'deleteFolderResilient') !== false ||
                strpos($line, 'GoogleDrive') !== false ||
                strpos($line, 'Service Account') !== false ||
                strpos($line, 'ERROR') !== false) {
                echo trim($line) . "\n";
                $foundRelevant = true;
            }
        }

        if (!$foundRelevant) {
            echo "No se encontraron logs relevantes recientes\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Prueba completada ===\n";
