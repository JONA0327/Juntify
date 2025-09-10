<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Verificar si la columna current_organization_id existe
$columnExists = DB::select("SHOW COLUMNS FROM users LIKE 'current_organization_id'");

if (!empty($columnExists)) {
    echo "La columna current_organization_id ya existe.\n";

    // Insertar el registro de migración manualmente
    try {
        DB::table('migrations')->insert([
            'migration' => '2025_08_30_000004_alter_users_add_current_organization_id',
            'batch' => 16
        ]);
        echo "Migración marcada como ejecutada exitosamente.\n";
    } catch (Exception $e) {
        echo "Error o migración ya existe: " . $e->getMessage() . "\n";
    }
} else {
    echo "La columna current_organization_id NO existe. Necesita ejecutar la migración.\n";
}
