<?php

require_once 'vendor/autoload.php';

use App\Services\GoogleDriveService;
use App\Models\User;
use App\Models\OrganizationContainerFolder;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba de permisos de Google Drive ===\n\n";

try {
    // Buscar un usuario que tenga token de Google Drive
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No se encontró ningún usuario con token de Google Drive\n";
        exit(1);
    }

    echo "✅ Usuario encontrado: {$user->username}\n";

    // Buscar una carpeta de contenedor para probar
    $containerFolder = OrganizationContainerFolder::first();
    if (!$containerFolder) {
        echo "❌ No se encontró ninguna carpeta de contenedor para probar\n";
        exit(1);
    }

    echo "✅ Carpeta de contenedor encontrada: {$containerFolder->name} (ID: {$containerFolder->google_id})\n";

    // Configurar el servicio de Google Drive
    $driveService = new GoogleDriveService();

    // Configurar el token (simulando lo que hace el trait)
    $googleToken = $user->googleToken;
    $tokenData = $googleToken->access_token;
    if (is_string($tokenData)) {
        $decoded = json_decode($tokenData, true);
        $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
    }

    $driveService->setAccessToken($tokenData);
    echo "✅ Token de Google Drive configurado\n";

    // Verificar información de la carpeta
    echo "\n--- Información de la carpeta ---\n";
    $folderInfo = $driveService->getFileInfo($containerFolder->google_id);
    if ($folderInfo) {
        echo "✅ Carpeta existe en Google Drive\n";
        echo "   Nombre: {$folderInfo->getName()}\n";
        echo "   Tipo: {$folderInfo->getMimeType()}\n";
        echo "   Propietario: " . json_encode($folderInfo->getOwners()) . "\n";

        // Verificar permisos
        echo "\n--- Verificando permisos ---\n";
        $permissions = $folderInfo->getPermissions();
        if ($permissions) {
            foreach ($permissions as $permission) {
                echo "   Permiso: {$permission->getRole()} para {$permission->getType()}\n";
            }
        } else {
            echo "   No se pudieron obtener los permisos\n";
        }
    } else {
        echo "❌ La carpeta no existe en Google Drive\n";
        exit(1);
    }

    // Intentar eliminar la carpeta (modo de prueba)
    echo "\n--- Probando eliminación ---\n";
    echo "⚠️  NOTA: Esta es una prueba real, la carpeta SE ELIMINARÁ\n";
    echo "¿Continuar? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);

    if (strtolower($input) === 'y') {
        try {
            $driveService->deleteFolder($containerFolder->google_id);
            echo "✅ Carpeta eliminada exitosamente\n";
        } catch (Exception $e) {
            echo "❌ Error al eliminar carpeta: {$e->getMessage()}\n";
            echo "   Código de error: {$e->getCode()}\n";
        }
    } else {
        echo "Prueba cancelada\n";
    }

} catch (Exception $e) {
    echo "❌ Error durante la prueba: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Fin de la prueba ===\n";
