<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\Schema;

$columns = Schema::getColumnListing('tasks_laravel');
echo "Columnas en tasks_laravel: " . implode(', ', $columns) . "\n";

// También verificar si username existe
$hasUsername = Schema::hasColumn('tasks_laravel', 'username');
echo "Tiene columna username: " . ($hasUsername ? 'SÍ' : 'NO') . "\n";
