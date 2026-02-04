<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "VERIFICANDO TABLAS DE REUNIONES\n";
echo "================================\n\n";

// Verificar transcriptions_laravel
echo "1. TABLA: transcriptions_laravel\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE transcriptions_laravel');
    foreach($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    
    $count = Illuminate\Support\Facades\DB::table('transcriptions_laravel')->count();
    echo "   Total registros: $count\n";
    
    if ($count > 0) {
        $sample = Illuminate\Support\Facades\DB::table('transcriptions_laravel')->first();
        echo "   Ejemplo: ID={$sample->id}, meeting_name=" . ($sample->meeting_name ?? 'N/A') . "\n";
    }
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n2. TABLA: meeting_groups\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE meeting_groups');
    foreach($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    $count = Illuminate\Support\Facades\DB::table('meeting_groups')->count();
    echo "   Total registros: $count\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. TABLA: meeting_group_user\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE meeting_group_user');
    foreach($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    $count = Illuminate\Support\Facades\DB::table('meeting_group_user')->count();
    echo "   Total registros: $count\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n4. TABLA: meetings\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE meetings');
    foreach($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    $count = Illuminate\Support\Facades\DB::table('meetings')->count();
    echo "   Total registros: $count\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n5. TABLA: meeting_transcriptions\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE meeting_transcriptions');
    foreach($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    $count = Illuminate\Support\Facades\DB::table('meeting_transcriptions')->count();
    echo "   Total registros: $count\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
