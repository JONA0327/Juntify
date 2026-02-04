<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Verificando reuniones ===\n\n";

// Total de reuniones
$total = DB::table('transcriptions_laravel')->count();
echo "Total reuniones en BD: $total\n\n";

// Primeras 5 reuniones
echo "Primeras 5 reuniones:\n";
$meetings = DB::table('transcriptions_laravel')
    ->select('id', 'username', 'meeting_name', 'created_at')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach ($meetings as $m) {
    echo "  - ID: {$m->id} | User: {$m->username} | Name: {$m->meeting_name}\n";
}

echo "\n";

// Usuarios distintos
$users = DB::table('transcriptions_laravel')
    ->select('username')
    ->distinct()
    ->get();

echo "Usuarios con reuniones:\n";
foreach ($users as $u) {
    $count = DB::table('transcriptions_laravel')->where('username', $u->username)->count();
    echo "  - {$u->username}: $count reuniones\n";
}
