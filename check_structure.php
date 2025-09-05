<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Estructura actualizada de la tabla google_tokens:\n\n";

$columns = \Illuminate\Support\Facades\DB::select('DESCRIBE google_tokens');
foreach ($columns as $col) {
    echo "- {$col->Field}: {$col->Type} (Null: {$col->Null})\n";
}
