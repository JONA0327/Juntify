<?php

require_once 'vendor/autoload.php';

use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba FINAL de eliminación de organización ===\n\n";

try {
    // 1. Usar una organización existente
    $testOrg = Organization::first();
    if (!$testOrg) {
        echo "❌ No hay organizaciones en la base de datos\n";
        exit(1);
    }

    echo "✅ Usando organización existente: {$testOrg->name} (ID: {$testOrg->id})\n";

    // 2. Crear carpetas de prueba en Drive (que simularán las carpetas de la organización)
    $sa = app(GoogleServiceAccount::class);
    $testFolderId = $sa->createFolder('TEST_ORG_MAIN_' . time());
    echo "✅ Carpeta principal de prueba creada en Drive: {$testFolderId}\n";

    // Crear una subcarpeta también
    $subFolderId = $sa->createFolder('TEST_ORG_SUB_' . time(), $testFolderId);
    echo "✅ Subcarpeta de prueba creada en Drive: {$subFolderId}\n";

    // 4. Obtener usuario para la eliminación
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No hay usuario con token\n";
        exit(1);
    }

    echo "✅ Usuario para eliminación: {$user->email}\n";

    // 5. Simular la eliminación de la organización (solo la parte de Drive)
    echo "\n🔄 Iniciando eliminación de carpetas de Drive...\n";

    // Usar reflexión para acceder al método privado
    $googleDriveService = app(\App\Services\GoogleDriveService::class);
    $controller = new \App\Http\Controllers\OrganizationController($googleDriveService);
    $reflector = new ReflectionClass($controller);
    $method = $reflector->getMethod('deleteOrganizationFoldersFromDrive');
    $method->setAccessible(true);

    // Ejecutar la eliminación
    $method->invoke($controller, $testOrg, $user);

    echo "✅ Método de eliminación ejecutado\n";

    // 6. Verificar si las carpetas fueron eliminadas de Drive
    echo "\n--- Verificando eliminación en Drive ---\n";

    // Intentar acceder a la carpeta principal
    try {
        $fileInfo = $sa->getFileInfo($testFolderId);
        echo "❌ PROBLEMA: La carpeta principal AÚN EXISTE: {$fileInfo->getName()}\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'File not found') !== false ||
            strpos($e->getMessage(), '404') !== false) {
            echo "✅ ÉXITO: Carpeta principal eliminada correctamente\n";
        } else {
            echo "⚠️  Error verificando carpeta principal: {$e->getMessage()}\n";
        }
    }

    // Intentar acceder a la subcarpeta
    try {
        $fileInfo = $sa->getFileInfo($subFolderId);
        echo "❌ PROBLEMA: La subcarpeta AÚN EXISTE: {$fileInfo->getName()}\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'File not found') !== false ||
            strpos($e->getMessage(), '404') !== false) {
            echo "✅ ÉXITO: Subcarpeta eliminada correctamente\n";
        } else {
            echo "⚠️  Error verificando subcarpeta: {$e->getMessage()}\n";
        }
    }

    // 7. Limpiar carpetas restantes manualmente
    echo "\n🧹 Limpiando carpetas de prueba restantes...\n";
    try {
        $sa->deleteFile($testFolderId);
        echo "✅ Carpeta principal eliminada manualmente\n";
    } catch (\Exception $e) {
        echo "ℹ️  Carpeta principal ya eliminada o no accesible\n";
    }

    try {
        $sa->deleteFile($subFolderId);
        echo "✅ Subcarpeta eliminada manualmente\n";
    } catch (\Exception $e) {
        echo "ℹ️  Subcarpeta ya eliminada o no accesible\n";
    }

    // 8. Verificar logs
    echo "\n--- Verificando logs recientes ---\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $command = 'Get-Content "' . $logFile . '" -Tail 10 | Select-String -Pattern "Organization.*deletion|deleted.*organization|GoogleDrive.*delete" -Context 1';
        $output = shell_exec("powershell -Command \"$command\"");
        if ($output) {
            echo $output;
        } else {
            echo "No se encontraron logs relevantes en las últimas 10 líneas\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error durante la prueba: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Prueba final completada ===\n";
