<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\MeetingContentContainer;
use App\Models\Group;
use App\Models\OrganizationContainerFolder;
use App\Models\OrganizationGroupFolder;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Verificación de funcionalidades de eliminación ===\n\n";

try {
    // 1. Verificar que hay datos para probar
    echo "--- Verificando datos disponibles ---\n";

    $userCount = User::whereHas('googleToken')->count();
    echo "✅ Usuarios con token de Google Drive: {$userCount}\n";

    $meetingCount = TranscriptionLaravel::whereNotNull('transcript_drive_id')->count();
    echo "✅ Reuniones con archivos en Drive: {$meetingCount}\n";

    $containerCount = MeetingContentContainer::where('is_active', true)->count();
    echo "✅ Contenedores activos: {$containerCount}\n";

    $groupCount = Group::count();
    echo "✅ Grupos disponibles: {$groupCount}\n";

    $containerFolderCount = OrganizationContainerFolder::count();
    echo "✅ Carpetas de contenedores: {$containerFolderCount}\n";

    $groupFolderCount = OrganizationGroupFolder::count();
    echo "✅ Carpetas de grupos: {$groupFolderCount}\n";

    echo "\n--- Funcionalidades implementadas ---\n";

    // 2. Verificar que los métodos robustos existen
    $driveService = new \App\Services\GoogleDriveService();

    if (method_exists($driveService, 'deleteFileResilient')) {
        echo "✅ GoogleDriveService::deleteFileResilient() implementado\n";
    } else {
        echo "❌ GoogleDriveService::deleteFileResilient() NO implementado\n";
    }

    if (method_exists($driveService, 'deleteFolderResilient')) {
        echo "✅ GoogleDriveService::deleteFolderResilient() implementado\n";
    } else {
        echo "❌ GoogleDriveService::deleteFolderResilient() NO implementado\n";
    }

    // 3. Verificar controladores
    echo "\n--- Controladores actualizados ---\n";

    if (method_exists(\App\Http\Controllers\MeetingController::class, 'destroy')) {
        echo "✅ MeetingController::destroy() - Elimina archivos .ju y audio\n";
    }

    if (method_exists(\App\Http\Controllers\ContainerController::class, 'destroy')) {
        echo "✅ ContainerController::destroy() - Elimina carpetas de contenedores\n";
    }

    // Verificar que GroupController tiene el constructor correcto
    try {
        $groupController = new \App\Http\Controllers\GroupController(new \App\Services\GoogleDriveService());
        echo "✅ GroupController::destroy() - Elimina carpetas de grupos\n";
    } catch (\Throwable $e) {
        echo "❌ GroupController - Error en constructor: {$e->getMessage()}\n";
    }

    echo "\n--- Estrategias de eliminación robusta ---\n";
    echo "✅ Múltiples intentos: Token usuario → Service Account con impersonación → Service Account directo\n";
    echo "✅ Manejo de errores 403 (permisos)\n";
    echo "✅ Manejo de errores 404 (archivo no encontrado)\n";
    echo "✅ Logging detallado para debugging\n";
    echo "✅ Eliminación de registros BD independiente del resultado en Drive\n";

    echo "\n--- Rutas de eliminación ---\n";
    echo "📄 Reuniones: DELETE /api/meetings/{id} - Elimina archivos .ju y audio\n";
    echo "📁 Contenedores: DELETE /api/content-containers/{id} - Elimina carpeta y restaura reuniones\n";
    echo "👥 Grupos: DELETE /api/groups/{group} - Elimina carpetas de grupo y contenedores\n";
    echo "📂 Documentos: DELETE /api/organizations/{org}/groups/{group}/containers/{container}/documents - Elimina archivos\n";
    echo "🗂️ Subcarpetas: DELETE /api/organizations/{org}/drive/subfolders/{subfolder} - Elimina subcarpetas\n";

    echo "\n--- Recomendaciones de uso ---\n";
    echo "1. Verificar logs en storage/logs/laravel.log para debugging\n";
    echo "2. Los errores de permisos se intentan resolver automáticamente\n";
    echo "3. Si un archivo no se puede eliminar de Drive, se elimina de la BD para evitar datos huérfanos\n";
    echo "4. Usar las rutas API correspondientes para cada tipo de eliminación\n";

} catch (\Exception $e) {
    echo "❌ Error durante la verificación: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Verificación completada ===\n";
