<?php
/**
 * Marca todas las migraciones presentes en database/migrations como ejecutadas
 * sin intentar correrlas, útil cuando ya tienes las tablas creadas manualmente.
 * Uso: php tools/mark_all_existing_migrations.php [--batch=N]
 */
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$batch = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batch = (int) substr($arg, 8);
    }
}
if ($batch === null) {
    // Si no se pasa batch, usar el siguiente número disponible
    $current = DB::table('migrations')->max('batch');
    $batch = $current ? ($current + 1) : 1;
}

$migrationsDir = __DIR__ . '/../database/migrations';
$files = glob($migrationsDir . '/*.php');
if (!$files) {
    echo "No se encontraron migraciones.\n";
    exit(0);
}

$existing = DB::table('migrations')->pluck('migration')->all();
$existingMap = array_flip($existing);

$inserted = 0; $skipped = 0; $errors = 0;
foreach ($files as $file) {
    $base = basename($file, '.php');
    // Saltar si ya existe
    if (isset($existingMap[$base])) { $skipped++; continue; }

    try {
        DB::table('migrations')->insert([
            'migration' => $base,
            'batch' => $batch,
        ]);
        $inserted++;
    } catch (Throwable $e) {
        $errors++;
        echo "Error insertando $base: {$e->getMessage()}\n";
    }
}

echo "Batch usado: $batch\n";
echo "Insertadas: $inserted | Saltadas (ya estaban): $skipped | Errores: $errors\n";

echo "Listo. Ahora php artisan migrate debería no intentar recrear tablas existentes.\n";
