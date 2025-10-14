<?php

require_once 'vendor/autoload.php';

use App\Models\Organization;
use App\Models\OrganizationGoogleToken;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba de eliminación de organización REAL ===\n\n";

try {
    // 1. Encontrar una organización con token
    $org = Organization::whereHas('googleToken')->first();
    if (!$org) {
        echo "❌ No hay organizaciones con token\n";
        // Crear organización de prueba
        $org = Organization::create([
            'name' => 'TEST_DELETE_ORG_' . time(),
            'description' => 'Organización de prueba para eliminación'
        ]);
        echo "✅ Organización de prueba creada: {$org->name}\n";
    } else {
        echo "✅ Organización encontrada: {$org->name}\n";
    }

    // 2. Verificar token de la organización
    $orgToken = $org->googleToken;
    if ($orgToken) {
        echo "✅ Token encontrado para la organización\n";
        echo "   Token válido hasta: " . ($orgToken->expires_at ?? 'No definido') . "\n";

        // Verificar si el token ha expirado
        if ($orgToken->expires_at && now()->gt($orgToken->expires_at)) {
            echo "⚠️  Token EXPIRADO\n";
        } else {
            echo "✅ Token aún válido\n";
        }
    } else {
        echo "❌ No hay token para esta organización\n";
    }

    // 3. Buscar carpetas de la organización en Drive
    echo "\n--- Buscando carpetas de la organización ---\n";

    // Usar Service Account para buscar
    $sa = app(GoogleServiceAccount::class);

    // Crear carpeta de prueba directamente
    echo "� Creando carpeta de prueba para simular eliminación...\n";
    $testFolderId = $sa->createFolder($org->name . '_TEST_FOLDER_' . time());
    echo "✅ Carpeta de prueba creada: {$testFolderId}\n";

    // 4. Probar eliminación usando el método de OrganizationController
    if ($testFolderId) {
        echo "\n--- Probando eliminación con método del controller ---\n";

        // Simular el método deleteOrganizationFoldersFromDrive
        $driveService = new GoogleDriveService();

        // Intentar con token de organización si existe
        if ($orgToken && $orgToken->access_token) {
            echo "🔄 Probando con token de organización...\n";
            try {
                $tokenData = $orgToken->access_token;
                if (is_string($tokenData)) {
                    $decoded = json_decode($tokenData, true);
                    $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
                }

                $driveService->setAccessToken($tokenData);
                $driveService->deleteFile($testFolderId);
                echo "✅ ÉXITO: Eliminado con token de organización\n";
                $testFolderId = null;

            } catch (\Exception $e) {
                echo "❌ FALLO con token de organización: {$e->getMessage()}\n";
            }
        }

        // Si falla, probar con Service Account
        if ($testFolderId) {
            echo "🔄 Probando con Service Account como fallback...\n";
            try {
                $sa->deleteFile($testFolderId);
                echo "✅ ÉXITO: Eliminado con Service Account\n";
                $testFolderId = null;

            } catch (\Exception $e) {
                echo "❌ FALLO con Service Account: {$e->getMessage()}\n";
            }
        }
    }

    // 5. Verificar si queda algo
    if ($testFolderId) {
        echo "\n❌ PROBLEMA: La carpeta AÚN EXISTE con ID: {$testFolderId}\n";
        echo "🛠️  Esto indica un problema real de permisos o configuración\n";

        // Intentar obtener información del archivo
        try {
            $fileInfo = $sa->getFile($testFolderId);
            echo "📄 Información del archivo:\n";
            echo "   Nombre: {$fileInfo->getName()}\n";
            echo "   Propietarios: " . json_encode($fileInfo->getOwners()) . "\n";

        } catch (\Exception $e) {
            echo "❌ No se pudo obtener información: {$e->getMessage()}\n";
        }
    } else {
        echo "\n✅ ÉXITO: La carpeta fue eliminada correctamente\n";
    }

    // 6. Verificar logs recientes
    echo "\n--- Verificando logs recientes ---\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -10);

        foreach ($recentLines as $line) {
            if (strpos($line, 'GoogleDrive') !== false ||
                strpos($line, 'deleteFile') !== false ||
                strpos($line, 'ERROR') !== false) {
                echo "📝 " . trim($line) . "\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ Error durante la prueba: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Prueba de organización completada ===\n";
