<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 VERIFICANDO ESTRUCTURA DE TABLAS DE REUNIONES\n";
echo "===============================================\n\n";

// Verificar tabla transcriptions_laravel
echo "📋 Tabla transcriptions_laravel:\n";
try {
    $columns = Illuminate\Support\Facades\Schema::getColumnListing('transcriptions_laravel');
    foreach ($columns as $column) {
        echo "   - {$column}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

echo "\n📋 Tabla transcriptions_temp:\n";
try {
    $columns = Illuminate\Support\Facades\Schema::getColumnListing('transcriptions_temp');
    foreach ($columns as $column) {
        echo "   - {$column}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

// Buscar todas las reuniones del usuario BNI por diferentes campos posibles
echo "\n🔍 BUSCANDO REUNIONES DEL USUARIO BNI:\n";
$userEmail = 'CongresoBNI@gmail.com';
$user = App\Models\User::where('email', $userEmail)->first();

if ($user) {
    echo "Usuario ID: {$user->id}\n\n";

    // Intentar diferentes campos para buscar reuniones
    $possibleFields = ['user_id', 'owner_id', 'creator_id', 'email'];

    foreach ($possibleFields as $field) {
        echo "🔍 Buscando por campo '{$field}':\n";

        try {
            // Buscar en transcriptions_laravel
            $regularCount = App\Models\TranscriptionLaravel::where($field, $user->id)->count();
            echo "   - transcriptions_laravel: {$regularCount} reuniones\n";
        } catch (Exception $e) {
            echo "   - transcriptions_laravel: Campo no existe\n";
        }

        try {
            // Buscar en transcriptions_temp
            $tempCount = App\Models\TranscriptionTemp::where($field, $user->id)->count();
            echo "   - transcriptions_temp: {$tempCount} reuniones\n";
        } catch (Exception $e) {
            echo "   - transcriptions_temp: Campo no existe\n";
        }

        echo "\n";
    }

    // También buscar por email si existe ese campo
    try {
        echo "🔍 Buscando por email '{$userEmail}':\n";
        $regularByEmail = App\Models\TranscriptionLaravel::where('email', $userEmail)->get();
        $tempByEmail = App\Models\TranscriptionTemp::where('email', $userEmail)->get();

        echo "   - transcriptions_laravel: " . count($regularByEmail) . " reuniones\n";
        echo "   - transcriptions_temp: " . count($tempByEmail) . " reuniones\n";

        if (count($regularByEmail) > 0) {
            echo "\n📋 Reuniones regulares encontradas:\n";
            foreach ($regularByEmail as $meeting) {
                echo "   - ID: {$meeting->id}, Título: " . ($meeting->title ?? 'Sin título') . "\n";
            }
        }

        if (count($tempByEmail) > 0) {
            echo "\n📋 Reuniones temporales encontradas:\n";
            foreach ($tempByEmail as $meeting) {
                echo "   - ID: {$meeting->id}, Título: " . ($meeting->title ?? 'Sin título') . "\n";
            }
        }

    } catch (Exception $e) {
        echo "   ❌ No se puede buscar por email: {$e->getMessage()}\n";
    }
}
