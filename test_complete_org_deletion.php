<?php

require_once 'vendor/autoload.php';

use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba FINAL COMPLETA de eliminación de organización ===\n\n";

try {
    // 1. Obtener organización existente
    $org = Organization::first();
    if (!$org) {
        echo "❌ No hay organizaciones\n";
        exit(1);
    }

    // 2. Obtener usuario
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No hay usuario con token\n";
        exit(1);
    }

    echo "✅ Organización: {$org->name} (ID: {$org->id})\n";
    echo "✅ Usuario: {$user->email}\n";

    // 3. Crear carpetas de prueba que simularán las carpetas de la organización
    $sa = app(GoogleServiceAccount::class);

    $mainFolderId = $sa->createFolder("MAIN_ORG_{$org->id}_" . time());
    $groupFolderId = $sa->createFolder("GROUP_ORG_{$org->id}_" . time(), $mainFolderId);
    $containerFolderId = $sa->createFolder("CONTAINER_ORG_{$org->id}_" . time(), $mainFolderId);
    $subFolderId = $sa->createFolder("SUB_ORG_{$org->id}_" . time(), $groupFolderId);

    echo "✅ Carpetas de prueba creadas:\n";
    echo "   📁 Principal: {$mainFolderId}\n";
    echo "   📁 Grupo: {$groupFolderId}\n";
    echo "   📁 Contenedor: {$containerFolderId}\n";
    echo "   📁 Subcarpeta: {$subFolderId}\n";

    // 4. Simular la eliminación usando deleteFolderResilient para cada tipo de carpeta
    echo "\n🔄 Simulando eliminación de carpetas organizacionales...\n";

    $driveService = app(\App\Services\GoogleDriveService::class);
    $deletedCount = 0;
    $failedCount = 0;

    $foldersToDelete = [
        'Principal' => $mainFolderId,
        'Grupo' => $groupFolderId,
        'Contenedor' => $containerFolderId,
        'Subcarpeta' => $subFolderId
    ];

    foreach ($foldersToDelete as $type => $folderId) {
        echo "🔄 Eliminando carpeta {$type}...\n";
        $success = $driveService->deleteFolderResilient($folderId, $user->email);

        if ($success) {
            echo "✅ Carpeta {$type} eliminada exitosamente\n";
            $deletedCount++;

            // Verificar eliminación
            try {
                $sa->getFileInfo($folderId);
                echo "❌ ERROR: Carpeta {$type} AÚN EXISTE después de eliminación\n";
                $failedCount++;
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'File not found') !== false) {
                    echo "✅ Confirmado: Carpeta {$type} eliminada del Drive\n";
                }
            }
        } else {
            echo "❌ FALLO: No se pudo eliminar carpeta {$type}\n";
            $failedCount++;
        }
    }

    // 5. Resumen
    echo "\n--- RESUMEN DE RESULTADOS ---\n";
    echo "✅ Carpetas eliminadas exitosamente: {$deletedCount}\n";
    echo "❌ Carpetas que fallaron: {$failedCount}\n";
    echo "📊 Total procesadas: " . count($foldersToDelete) . "\n";

    if ($failedCount === 0) {
        echo "\n🎉 ¡ÉXITO COMPLETO! Todas las carpetas fueron eliminadas correctamente\n";
        echo "✅ El sistema de eliminación de organizaciones está funcionando\n";
    } else {
        echo "\n⚠️  Hay algunos problemas pendientes que revisar\n";
    }

    // 6. Mostrar logs recientes
    echo "\n--- Logs de eliminación ---\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -20);

        foreach ($recentLines as $line) {
            if (strpos($line, 'GoogleDriveService') !== false ||
                strpos($line, 'eliminación robusta') !== false ||
                strpos($line, 'eliminada con service account') !== false) {
                echo "📝 " . trim($line) . "\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Prueba final completa terminada ===\n";
