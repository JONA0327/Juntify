<?php
// =====================================================
// COMANDO PARA VERIFICAR EL ESTADO DE LAS COLAS
// =====================================================
// Ejecutar con: php monitor-queue.php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 MONITOR DE COLAS JUNTIFY\n";
echo "==========================\n\n";

try {
    $db = DB::connection();
    
    // Trabajos pendientes
    $pending = $db->table('jobs')->count();
    echo "📊 Trabajos pendientes: {$pending}\n";
    
    // Trabajos por cola
    $queues = $db->table('jobs')
        ->select('queue', DB::raw('count(*) as count'))
        ->groupBy('queue')
        ->get();
    
    if ($queues->isNotEmpty()) {
        echo "\n📋 Por cola:\n";
        foreach ($queues as $queue) {
            echo "   - {$queue->queue}: {$queue->count} trabajos\n";
        }
    }
    
    // Trabajos más antiguos
    $oldest = $db->table('jobs')
        ->orderBy('created_at')
        ->first();
    
    if ($oldest) {
        $age = now()->diffInMinutes($oldest->created_at);
        echo "\n⏰ Trabajo más antiguo: hace {$age} minutos\n";
        
        if ($age > 10) {
            echo "⚠️  ADVERTENCIA: Hay trabajos pendientes desde hace más de 10 minutos\n";
            echo "   Verifica que los workers estén ejecutándose correctamente\n";
        }
    }
    
    // Trabajos fallidos (si existe la tabla failed_jobs)
    try {
        $failed = $db->table('failed_jobs')->count();
        if ($failed > 0) {
            echo "\n❌ Trabajos fallidos: {$failed}\n";
            echo "   Revisa con: php artisan queue:failed\n";
        }
    } catch (Exception $e) {
        // La tabla failed_jobs no existe, está bien
    }
    
    echo "\n✅ Monitoreo completado\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}