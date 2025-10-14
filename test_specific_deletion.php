<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba específica de eliminación de Google Drive ===\n\n";

try {
    // 1. Obtener usuario
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No hay usuarios con token\n";
        exit(1);
    }

    echo "✅ Usuario: {$user->email}\n";

    // 2. Configurar GoogleDriveService
    $driveService = new GoogleDriveService();
    $googleToken = $user->googleToken;
    $tokenData = $googleToken->access_token;
    if (is_string($tokenData)) {
        $decoded = json_decode($tokenData, true);
        $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
    }
    $driveService->setAccessToken($tokenData);

    // 3. Listar carpetas para encontrar la carpeta "AUTOMATIZADOR"
    echo "\n--- Buscando carpetas sospechosas ---\n";
    $folders = $driveService->listFolders();
    $testFolderId = null;

    foreach ($folders as $folder) {
        if (strpos($folder->getName(), 'AUTOMATIZADOR') !== false ||
            strpos($folder->getName(), 'test') !== false ||
            strpos($folder->getName(), 'Test') !== false) {
            echo "📁 Carpeta encontrada: {$folder->getName()} (ID: {$folder->getId()})\n";
            if (!$testFolderId) {
                $testFolderId = $folder->getId();
            }
        }
    }

    if (!$testFolderId) {
        echo "⚠️  No se encontraron carpetas de prueba. Creando una...\n";
        $testFolderId = $driveService->createFolder('TEST_DELETE_' . time());
        echo "✅ Carpeta de prueba creada: {$testFolderId}\n";
    }

    // 4. Probar eliminación con diferentes métodos
    echo "\n--- Probando eliminación con GoogleDriveService ---\n";

    // Método 1: deleteFile estándar
    try {
        echo "🔄 Intentando con deleteFile()...\n";
        $driveService->deleteFile($testFolderId);
        echo "✅ ÉXITO: deleteFile() funcionó\n";
        $testFolderId = null; // Ya eliminado
    } catch (\Exception $e) {
        echo "❌ FALLO deleteFile(): {$e->getMessage()}\n";

        // Método 2: deleteFileResilient
        try {
            echo "🔄 Intentando con deleteFileResilient()...\n";
            $success = $driveService->deleteFileResilient($testFolderId, $user->email);
            if ($success) {
                echo "✅ ÉXITO: deleteFileResilient() funcionó\n";
                $testFolderId = null; // Ya eliminado
            } else {
                echo "❌ FALLO: deleteFileResilient() retornó false\n";
            }
        } catch (\Exception $e2) {
            echo "❌ FALLO deleteFileResilient(): {$e2->getMessage()}\n";
        }
    }

    // Si aún tenemos el archivo, probar Service Account directo
    if ($testFolderId) {
        echo "\n--- Probando eliminación con Service Account ---\n";
        try {
            $sa = app(GoogleServiceAccount::class);

            // Probar sin impersonation
            echo "🔄 Intentando Service Account sin impersonation...\n";
            $sa->deleteFile($testFolderId);
            echo "✅ ÉXITO: Service Account directo funcionó\n";
            $testFolderId = null;

        } catch (\Exception $e) {
            echo "❌ FALLO Service Account directo: {$e->getMessage()}\n";

            // Probar con impersonation
            try {
                echo "🔄 Intentando Service Account con impersonation...\n";
                $sa->impersonate($user->email);
                $sa->deleteFile($testFolderId);
                echo "✅ ÉXITO: Service Account con impersonation funcionó\n";
                $testFolderId = null;

            } catch (\Exception $e2) {
                echo "❌ FALLO Service Account con impersonation: {$e2->getMessage()}\n";
            }
        }
    }

    // 5. Diagnóstico de permisos
    if ($testFolderId) {
        echo "\n--- Diagnóstico de permisos ---\n";
        try {
            $fileInfo = $driveService->getFileInfo($testFolderId);
            echo "📄 Información del archivo:\n";
            echo "   Nombre: {$fileInfo->getName()}\n";
            echo "   Propietario: " . json_encode($fileInfo->getOwners()) . "\n";
            echo "   Permisos: " . json_encode($fileInfo->getPermissions()) . "\n";
            echo "   Compartido: " . ($fileInfo->getShared() ? 'Sí' : 'No') . "\n";

        } catch (\Exception $e) {
            echo "❌ No se pudo obtener información del archivo: {$e->getMessage()}\n";
        }
    }

    // 6. Resumen de resultados
    echo "\n--- Resumen ---\n";
    if (!$testFolderId) {
        echo "✅ RESULTADO: Se logró eliminar el archivo/carpeta\n";
        echo "   Recomendación: Los métodos funcionan, revisar tokens en el sistema\n";
    } else {
        echo "❌ RESULTADO: NO se pudo eliminar el archivo/carpeta\n";
        echo "   Problema: Permisos insuficientes o configuración incorrecta\n";
        echo "   Archivo permanece con ID: {$testFolderId}\n";
        echo "\n🛠️  Acciones recomendadas:\n";
        echo "   1. Verificar que el usuario sea propietario de los archivos\n";
        echo "   2. Verificar permisos del Service Account\n";
        echo "   3. Verificar que los archivos estén en 'My Drive'\n";
        echo "   4. Revisar configuración de domain-wide delegation\n";
    }

} catch (\Exception $e) {
    echo "❌ Error durante la prueba: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Prueba completada ===\n";
