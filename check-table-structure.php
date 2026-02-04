<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "ESTRUCTURA DE LA TABLA 'empresa'\n";
echo "=================================\n";
$columns = Illuminate\Support\Facades\DB::connection('juntify_panels')->select('DESCRIBE empresa');
foreach($columns as $col) {
    echo "- {$col->Field} ({$col->Type})\n";
}

echo "\nESTRUCTURA DE LA TABLA 'integrantes_empresa'\n";
echo "=============================================\n";
$columns2 = Illuminate\Support\Facades\DB::connection('juntify_panels')->select('DESCRIBE integrantes_empresa');
foreach($columns2 as $col) {
    echo "- {$col->Field} ({$col->Type})\n";
}

echo "\nDATO DE EJEMPLO - empresa (id=2):\n";
echo "==================================\n";
$empresa = Illuminate\Support\Facades\DB::connection('juntify_panels')
    ->table('empresa')
    ->where('id', 2)
    ->first();
if ($empresa) {
    foreach($empresa as $key => $value) {
        echo "{$key}: {$value}\n";
    }
}

echo "\nINTEGRANTES de empresa_id=2:\n";
echo "============================\n";
$integrantes = Illuminate\Support\Facades\DB::connection('juntify_panels')
    ->table('integrantes_empresa')
    ->where('empresa_id', 2)
    ->get();
foreach($integrantes as $int) {
    echo "- iduser: {$int->iduser}, rol: {$int->rol}\n";
}
