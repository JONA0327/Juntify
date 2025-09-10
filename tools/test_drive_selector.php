<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Crear una aplicación básica para testing
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== Probando endpoints de Drive ===\n\n";

// Test 1: Endpoint personal
echo "1. Probando endpoint personal (/drive/sync-subfolders):\n";
try {
    $request = Request::create('/drive/sync-subfolders', 'GET');
    $response = $kernel->handle($request);

    echo "Status: " . $response->getStatusCode() . "\n";

    if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getContent(), true);
        if (isset($data['root_folder'])) {
            echo "✅ Carpeta personal encontrada: " . $data['root_folder']['name'] . "\n";
            echo "Google ID: " . $data['root_folder']['google_id'] . "\n";
        } else {
            echo "❌ No se encontró root_folder en la respuesta\n";
        }
    } else {
        echo "❌ Error: " . $response->getContent() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Verificar si hay organizaciones disponibles
echo "2. Verificando organizaciones disponibles:\n";
try {
    // Simular autenticación (esto es solo para prueba)
    $user = \App\Models\User::first();
    if ($user) {
        echo "Usuario de prueba: " . $user->username . "\n";

        $organizations = \App\Models\Organization::whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->orWhere('admin_id', $user->id)->get();

        echo "Organizaciones encontradas: " . $organizations->count() . "\n";

        foreach ($organizations as $org) {
            echo "- " . $org->nombre_organizacion . " (ID: " . $org->id . ")\n";

            // Intentar obtener la carpeta de la organización
            if ($org->folder) {
                echo "  ✅ Tiene carpeta: " . $org->folder->name . "\n";
            } else {
                echo "  ❌ No tiene carpeta configurada\n";
            }
        }
    } else {
        echo "❌ No se encontraron usuarios en la base de datos\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Prueba completada.\n";
echo "Los cambios en el selector de Drive deberían funcionar correctamente.\n";
echo "Visita la interfaz web para ver los nombres reales de las carpetas.\n";
