<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Limpiar fechas inválidas
$updated = DB::table('users')
    ->where('plan_expires_at', '0000-00-00 00:00:00')
    ->update(['plan_expires_at' => null]);

echo "Fechas actualizadas: $updated filas\n";

// También verificar otros posibles valores problemáticos
$updated2 = DB::table('users')
    ->whereRaw("plan_expires_at = '' OR plan_expires_at = '0000-00-00'")
    ->update(['plan_expires_at' => null]);

echo "Fechas adicionales actualizadas: $updated2 filas\n";
