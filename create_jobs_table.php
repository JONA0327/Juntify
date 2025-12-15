<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Obtener conexión a la base de datos
    $db = DB::connection();
    
    echo "🔗 Conectando a la base de datos...\n";
    
    // Verificar si la tabla jobs ya existe
    $jobsExists = $db->select("SHOW TABLES LIKE 'jobs'");
    
    if (empty($jobsExists)) {
        echo "📊 Creando tabla jobs...\n";
        
        // Crear tabla jobs
        $db->statement("
            CREATE TABLE `jobs` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `attempts` tinyint(3) unsigned NOT NULL,
              `reserved_at` int(10) unsigned DEFAULT NULL,
              `available_at` int(10) unsigned NOT NULL,
              `created_at` int(10) unsigned NOT NULL,
              PRIMARY KEY (`id`),
              KEY `jobs_queue_index` (`queue`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "✅ Tabla jobs creada exitosamente.\n";
    } else {
        echo "ℹ️  La tabla jobs ya existe.\n";
    }
    
    // Verificar si la migración ya está registrada
    $migrationExists = $db->select("SELECT * FROM migrations WHERE migration = '2025_12_15_005248_create_jobs_table'");
    
    if (empty($migrationExists)) {
        echo "📝 Registrando migración...\n";
        
        // Obtener el siguiente batch number
        $maxBatch = $db->select("SELECT COALESCE(MAX(batch), 0) + 1 as next_batch FROM migrations");
        $nextBatch = $maxBatch[0]->next_batch;
        
        // Insertar registro de migración
        $db->insert("INSERT INTO migrations (migration, batch) VALUES (?, ?)", [
            '2025_12_15_005248_create_jobs_table',
            $nextBatch
        ]);
        
        echo "✅ Migración registrada en batch {$nextBatch}.\n";
    } else {
        echo "ℹ️  La migración ya está registrada.\n";
    }
    
    echo "\n🎉 ¡Proceso completado exitosamente!\n";
    echo "🚀 Ahora puedes usar las funcionalidades de colas de Laravel.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}