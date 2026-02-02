<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Reparando tabla tasks_laravel...\n";

try {
    // Eliminar tabla corrupta
    DB::statement('DROP TABLE IF EXISTS tasks_laravel');
    echo "✓ Tabla eliminada\n";
    
    // Recrear tabla con estructura completa según TaskLaravel model
    DB::statement("
        CREATE TABLE tasks_laravel (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            meeting_id BIGINT UNSIGNED NULL,
            meeting_type VARCHAR(50) DEFAULT 'temporary',
            tarea TEXT NOT NULL,
            descripcion TEXT NULL,
            prioridad VARCHAR(50) DEFAULT 'media',
            fecha_inicio DATE NULL,
            fecha_limite DATE NULL,
            hora_limite VARCHAR(10) NULL,
            asignado VARCHAR(255) NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            assignment_status VARCHAR(50) DEFAULT 'pending',
            progreso INT DEFAULT 0,
            google_event_id VARCHAR(255) NULL,
            google_calendar_id VARCHAR(255) NULL,
            calendar_synced_at TIMESTAMP NULL,
            overdue_notified_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_meeting_id (meeting_id),
            INDEX idx_assigned_user_id (assigned_user_id),
            INDEX idx_assignment_status (assignment_status),
            INDEX idx_fecha_limite (fecha_limite)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Tabla recreada\n";
    
    // Verificar
    $result = DB::select("SHOW TABLE STATUS LIKE 'tasks_laravel'");
    echo "✓ Engine: " . $result[0]->Engine . "\n";
    echo "✓ Reparación completada exitosamente\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
