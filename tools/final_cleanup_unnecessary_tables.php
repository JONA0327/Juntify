<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LIMPIEZA FINAL DE TABLAS INNECESARIAS ===\n\n";

$tablesToDelete = [
    'ai_container_ju_caches',
    'ai_context_embeddings',
    'chat_message_user_deletions',
    'chat_user_deletions',
    'company_users',
    'group_drive_folders',
    'meeting_containers',
    'meeting_keywords',
    'orders',
    'plan_purchases',
    'user_subscriptions',
];

echo "🗑️  TABLAS A ELIMINAR:\n";
foreach ($tablesToDelete as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "  • {$table} (Registros: {$count})\n";
    }
}

echo "\n¿Continuar con la eliminación? (y/n): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'y') {
    echo "❌ Operación cancelada.\n";
    exit(0);
}

echo "\n🚀 Eliminando tablas...\n\n";

$deleted = 0;
foreach ($tablesToDelete as $table) {
    try {
        if (Schema::hasTable($table)) {
            Schema::drop($table);
            echo "✅ Eliminada: {$table}\n";
            $deleted++;
        }
    } catch (Exception $e) {
        echo "❌ Error eliminando {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Limpieza completada. Tablas eliminadas: {$deleted}\n";
