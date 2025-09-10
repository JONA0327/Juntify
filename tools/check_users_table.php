<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Estructura de la tabla users ===\n";

try {
    $columns = DB::select('DESCRIBE users');
    foreach ($columns as $column) {
        echo "- {$column->Field} ({$column->Type}) - {$column->Null} - {$column->Key} - {$column->Default}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Verificando columnas específicas ===\n";
$checkColumns = ['id', 'name', 'username', 'email', 'current_organization_id'];
foreach ($checkColumns as $col) {
    $exists = Schema::hasColumn('users', $col);
    echo "- {$col}: " . ($exists ? "✓ Existe" : "✗ No existe") . "\n";
}

echo "\n=== Primeros 3 usuarios ===\n";
try {
    $users = DB::table('users')->limit(3)->get();
    foreach ($users as $user) {
        echo "- ID: {$user->id}, Email: {$user->email}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
