<?php

require_once 'vendor/autoload.php';

use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\OrganizationGroupFolder;
use App\Models\OrganizationContainerFolder;
use App\Models\User;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Análisis de eliminación de organizaciones ===\n\n";

try {
    // 1. Verificar organizaciones existentes
    echo "--- Organizaciones existentes ---\n";
    $organizations = Organization::with(['groups', 'users'])->get();

    foreach ($organizations as $org) {
        echo "📋 Organización: {$org->nombre_organizacion} (ID: {$org->id})\n";
        echo "   Admin: {$org->admin_id}\n";
        echo "   Grupos: {$org->groups->count()}\n";
        echo "   Usuarios: {$org->users->count()}\n";

        // Verificar carpetas asociadas
        $orgFolders = OrganizationFolder::where('organization_id', $org->id)->count();
        echo "   Carpetas principales: {$orgFolders}\n";

        $subfolders = OrganizationSubfolder::whereHas('folder', function($query) use ($org) {
            $query->where('organization_id', $org->id);
        })->count();
        echo "   Subcarpetas: {$subfolders}\n";

        $groupFolders = OrganizationGroupFolder::where('organization_id', $org->id)->count();
        echo "   Carpetas de grupos: {$groupFolders}\n";

        $containerFolders = OrganizationContainerFolder::where('organization_id', $org->id)->count();
        echo "   Carpetas de contenedores: {$containerFolders}\n";

        // Verificar token de Google Drive
        $hasToken = $org->googleToken && $org->googleToken->isConnected();
        echo "   Token Google Drive: " . ($hasToken ? "✅ Conectado" : "❌ No conectado") . "\n";

        echo "\n";
    }

    if ($organizations->isEmpty()) {
        echo "⚠️  No hay organizaciones para analizar\n";
        exit(0);
    }

    echo "--- Estructura de eliminación implementada ---\n";
    echo "✅ 1. Eliminación de carpetas de contenedores (OrganizationContainerFolder)\n";
    echo "✅ 2. Eliminación de carpetas de grupos (OrganizationGroupFolder)\n";
    echo "✅ 3. Eliminación de subcarpetas organizacionales (OrganizationSubfolder)\n";
    echo "✅ 4. Eliminación de carpetas principales (OrganizationFolder)\n";
    echo "✅ 5. Desvinculación de usuarios\n";
    echo "✅ 6. Reset de current_organization_id\n";
    echo "✅ 7. Eliminación de la organización (con CASCADE automático)\n";

    echo "\n--- Jerarquía de carpetas en Google Drive ---\n";
    echo "📁 Carpeta Organización Principal\n";
    echo "├── 📁 Carpeta Grupo 1\n";
    echo "│   ├── 📁 Contenedor 1.1\n";
    echo "│   └── 📁 Contenedor 1.2\n";
    echo "├── 📁 Carpeta Grupo 2\n";
    echo "│   └── 📁 Contenedor 2.1\n";
    echo "└── 📁 Subcarpetas adicionales\n";

    echo "\n--- Estrategias de eliminación robusta ---\n";
    echo "🔄 Múltiples intentos por carpeta:\n";
    echo "   1. Token de la organización (si existe)\n";
    echo "   2. Token del usuario administrador\n";
    echo "   3. Service Account impersonando\n";
    echo "   4. Service Account directo\n";

    echo "\n--- Orden de eliminación ---\n";
    echo "1️⃣ Carpetas más específicas primero (contenedores)\n";
    echo "2️⃣ Carpetas de grupos\n";
    echo "3️⃣ Subcarpetas organizacionales\n";
    echo "4️⃣ Carpetas principales de la organización\n";
    echo "5️⃣ Registros de base de datos (CASCADE automático)\n";

    echo "\n--- Para probar la eliminación ---\n";
    echo "🚨 ADVERTENCIA: Esto eliminará COMPLETAMENTE la organización\n";
    echo "📝 Ruta API: DELETE /api/organizations/{organization}\n";
    echo "🔐 Permisos requeridos: Ser admin de la organización o tener rol 'administrador'\n";
    echo "📋 Logging: Verificar storage/logs/laravel.log para detalles\n";

    // Mostrar ejemplo de eliminación (sin ejecutar)
    if ($organizations->count() > 0) {
        $firstOrg = $organizations->first();
        echo "\n--- Ejemplo de eliminación ---\n";
        echo "Para eliminar '{$firstOrg->nombre_organizacion}' (ID: {$firstOrg->id}):\n";
        echo "\n";
        echo "curl -X DELETE \\\n";
        echo "  'http://tu-dominio/api/organizations/{$firstOrg->id}' \\\n";
        echo "  -H 'Authorization: Bearer TU_TOKEN' \\\n";
        echo "  -H 'Content-Type: application/json'\n";
        echo "\n";
        echo "O usando JavaScript:\n";
        echo "fetch('/api/organizations/{$firstOrg->id}', {\n";
        echo "  method: 'DELETE',\n";
        echo "  headers: {\n";
        echo "    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,\n";
        echo "    'Content-Type': 'application/json'\n";
        echo "  }\n";
        echo "});\n";
    }

    echo "\n--- Verificación post-eliminación ---\n";
    echo "✅ Verificar que NO existan registros en:\n";
    echo "   - organization_folders\n";
    echo "   - organization_subfolders\n";
    echo "   - organization_group_folders\n";
    echo "   - organization_container_folders\n";
    echo "   - organizations\n";
    echo "✅ Verificar que las carpetas NO existan en Google Drive\n";
    echo "✅ Verificar logs para confirmar eliminaciones exitosas\n";

} catch (\Exception $e) {
    echo "❌ Error durante el análisis: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Análisis completado ===\n";
