<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use Illuminate\Support\Facades\Log;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba DETALLADA del método deleteFolderResilient ===\n\n";

try {
    // 1. Crear carpeta de prueba
    $sa = app(GoogleServiceAccount::class);
    $testFolderId = $sa->createFolder('DEBUG_DELETE_' . time());
    echo "✅ Carpeta creada: {$testFolderId}\n";

    // 2. Obtener usuario
    $user = User::whereHas('googleToken')->first();
    echo "✅ Usuario: {$user->email}\n";

    // 3. Forzar escritura de log manual
    Log::info('MANUAL TEST: Iniciando prueba de eliminación', [
        'folder_id' => $testFolderId,
        'user_email' => $user->email
    ]);

    // 4. Ejecutar deleteFolderResilient con debug manual
    echo "\n🔄 Ejecutando deleteFolderResilient con debug manual...\n";

    $driveService = app(GoogleDriveService::class);

    // Configurar token del usuario primero
    $tokenData = $user->googleToken->access_token;
    if (is_string($tokenData)) {
        $decoded = json_decode($tokenData, true);
        $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
    }
    $driveService->setAccessToken($tokenData);

    // Intentar eliminación manual paso a paso
    echo "📋 Paso 1: Intentando con token de usuario...\n";
    try {
        $driveService->deleteFolder($testFolderId);
        echo "✅ deleteFolder() no lanzó excepción\n";

        // Verificar si realmente se eliminó
        try {
            $sa->getFileInfo($testFolderId);
            echo "❌ PROBLEMA: deleteFolder() no lanzó excepción pero la carpeta AÚN EXISTE\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'File not found') !== false) {
                echo "✅ Carpeta eliminada correctamente\n";
                $testFolderId = null; // Ya eliminada
            } else {
                echo "⚠️  Error verificando: {$e->getMessage()}\n";
            }
        }

    } catch (\Exception $e) {
        echo "❌ deleteFolder() falló: {$e->getMessage()}\n";

        // Intentar con Service Account
        if ($testFolderId) {
            echo "📋 Paso 2: Intentando con Service Account...\n";
            try {
                $sa->deleteFile($testFolderId);
                echo "✅ Service Account eliminó la carpeta\n";
                $testFolderId = null;
            } catch (\Exception $e2) {
                echo "❌ Service Account también falló: {$e2->getMessage()}\n";
            }
        }
    }

    // 5. Limpiar si quedó algo
    if ($testFolderId) {
        echo "\n🧹 Limpiando carpeta restante...\n";
        try {
            $sa->deleteFile($testFolderId);
            echo "✅ Limpieza exitosa\n";
        } catch (\Exception $e) {
            echo "❌ Error en limpieza: {$e->getMessage()}\n";
        }
    }

    // 6. Verificar logs después
    Log::info('MANUAL TEST: Prueba completada');

    echo "\n--- Verificando logs escritos ---\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        if (strpos($content, 'MANUAL TEST') !== false) {
            $lines = explode("\n", $content);
            $found = 0;
            for ($i = count($lines) - 1; $i >= 0 && $found < 5; $i--) {
                if (strpos($lines[$i], 'MANUAL TEST') !== false) {
                    echo "📝 " . trim($lines[$i]) . "\n";
                    $found++;
                }
            }
        } else {
            echo "❌ No se encontraron logs MANUAL TEST\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Línea: {$e->getLine()}\n";
}

echo "\n=== Prueba detallada completada ===\n";
