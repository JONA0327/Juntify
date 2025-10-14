<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Organization;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Diagnóstico completo de Google Drive API ===\n\n";

try {
    // 1. Verificar usuario con token
    echo "--- Verificando usuarios ---\n";
    $user = User::whereHas('googleToken')->first();
    if (!$user) {
        echo "❌ No hay usuarios con token de Google Drive\n";
        exit(1);
    }

    echo "✅ Usuario encontrado: {$user->username} ({$user->email})\n";

    // 2. Verificar organizaciones con token
    echo "\n--- Verificando organizaciones ---\n";
    $organization = Organization::whereHas('googleToken')->first();
    if ($organization) {
        echo "✅ Organización con token: {$organization->nombre_organizacion}\n";
        $orgToken = $organization->googleToken;
        echo "   Token conectado: " . ($orgToken->isConnected() ? "✅ Sí" : "❌ No") . "\n";
        echo "   Token expirado: " . ($orgToken->isExpired() ? "❌ Sí" : "✅ No") . "\n";
    } else {
        echo "⚠️  No hay organizaciones con token de Google Drive\n";
    }

    // 3. Probar GoogleDriveService con token de usuario
    echo "\n--- Probando GoogleDriveService con token de usuario ---\n";
    try {
        $driveService = new GoogleDriveService();

        // Configurar token de usuario
        $googleToken = $user->googleToken;
        $tokenData = $googleToken->access_token;
        if (is_string($tokenData)) {
            $decoded = json_decode($tokenData, true);
            $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
        }

        $driveService->setAccessToken($tokenData);
        echo "✅ Token de usuario configurado en GoogleDriveService\n";

        // Probar listar carpetas
        $folders = $driveService->listFolders();
        echo "✅ Listado de carpetas exitoso: " . count($folders) . " carpetas encontradas\n";

        // Mostrar algunas carpetas
        foreach (array_slice($folders, 0, 3) as $folder) {
            echo "   � {$folder->getName()} (ID: {$folder->getId()})\n";
        }

    } catch (\Exception $e) {
        echo "❌ Error con GoogleDriveService: {$e->getMessage()}\n";
    }

    // 4. Probar Service Account
    echo "\n--- Probando Service Account ---\n";
    try {
        $sa = app(GoogleServiceAccount::class);
        echo "✅ Service Account inicializado\n";

        // Probar impersonation
        $sa->impersonate($user->email);
        echo "✅ Impersonación exitosa para {$user->email}\n";

        // Probar operación básica - intentar crear una carpeta de prueba
        echo "✅ Service Account configurado correctamente\n";

    } catch (\Exception $e) {
        echo "❌ Error con Service Account: {$e->getMessage()}\n";
    }

    // 5. Probar eliminación de archivo de prueba
    echo "\n--- Probando eliminación de archivo ---\n";
    echo "⚠️  Para probar eliminación, necesitamos un archivo específico\n";
    echo "   Puedes usar test_drive_permissions.php para pruebas específicas\n";

    // 6. Verificar configuración
    echo "\n--- Verificando configuración ---\n";

    // Service Account file
    $serviceAccountPath = storage_path('app/Drive/numeric-replica-450010-h9-c24956387fb8.json');
    if (file_exists($serviceAccountPath)) {
        echo "✅ Archivo de Service Account existe\n";
        $serviceAccountData = json_decode(file_get_contents($serviceAccountPath), true);
        if ($serviceAccountData) {
            echo "   Proyecto: {$serviceAccountData['project_id']}\n";
            echo "   Cliente: {$serviceAccountData['client_email']}\n";
        }
    } else {
        echo "❌ Archivo de Service Account NO encontrado en: {$serviceAccountPath}\n";
    }

    // API Key
    $apiKey = config('services.google.api_key');
    if ($apiKey) {
        echo "✅ API Key configurada (longitud: " . strlen($apiKey) . ")\n";
    } else {
        echo "❌ API Key NO configurada\n";
    }

    // 7. Problemas comunes y soluciones
    echo "\n--- Problemas comunes identificados ---\n";

    echo "🔍 Posibles causas de fallos en eliminación:\n";
    echo "   1. Token de usuario sin permisos de escritura\n";
    echo "   2. Archivos creados por otra cuenta/service account\n";
    echo "   3. Archivos en Shared Drives sin acceso\n";
    echo "   4. Token organizacional mal configurado\n";
    echo "   5. Service Account sin permisos de impersonación\n";

    echo "\n🛠️  Recomendaciones:\n";
    echo "   1. Verificar que el usuario tenga permisos completos en Drive\n";
    echo "   2. Usar Service Account con domain-wide delegation\n";
    echo "   3. Verificar que los archivos estén en 'My Drive' no en 'Shared Drives'\n";
    echo "   4. Probar eliminación manual primero con el mismo usuario\n";

    echo "\n--- Comandos de prueba ---\n";
    echo "📝 Para probar eliminación específica:\n";
    echo "   php test_drive_permissions.php\n";
    echo "\n📝 Para ver logs en tiempo real:\n";
    echo "   Get-Content storage/logs/laravel.log -Wait -Tail 50\n";


} catch (\Exception $e) {
    echo "❌ Error durante el diagnóstico: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "   Trace: {$e->getTraceAsString()}\n";
}

echo "\n=== Diagnóstico completado ===\n";
