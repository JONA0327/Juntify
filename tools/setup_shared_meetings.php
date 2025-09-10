<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Crear instancia de Laravel para acceder a DB
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Verificar si shared_meetings existe
    $exists = DB::select("SHOW TABLES LIKE 'shared_meetings'");

    if (empty($exists)) {
        echo "Creando tabla shared_meetings...\n";

        DB::statement("
            CREATE TABLE shared_meetings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                meeting_id INT UNSIGNED NOT NULL,
                shared_by BIGINT UNSIGNED NOT NULL,
                shared_with BIGINT UNSIGNED NOT NULL,
                status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
                shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                responded_at TIMESTAMP NULL,
                message TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_share (meeting_id, shared_with)
            )
        ");
        echo "Tabla shared_meetings creada exitosamente.\n";
    } else {
        echo "Tabla shared_meetings ya existe.\n";
    }

    // Verificar estructura de notifications
    $notificationColumns = DB::select("DESCRIBE notifications");
    echo "Estructura actual de notifications:\n";
    foreach ($notificationColumns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }

    // Agregar columnas faltantes a notifications
    $hasFromUserId = collect($notificationColumns)->contains('Field', 'from_user_id');
    $hasType = collect($notificationColumns)->contains('Field', 'type');
    $hasTitle = collect($notificationColumns)->contains('Field', 'title');
    $hasMessage = collect($notificationColumns)->contains('Field', 'message');
    $hasRead = collect($notificationColumns)->contains('Field', 'read');

    if (!$hasFromUserId) {
        DB::statement("ALTER TABLE notifications ADD COLUMN from_user_id BIGINT UNSIGNED NULL AFTER user_id");
        echo "Agregada columna from_user_id.\n";
    }

    if (!$hasType) {
        DB::statement("ALTER TABLE notifications ADD COLUMN type VARCHAR(255) NOT NULL AFTER from_user_id");
        echo "Agregada columna type.\n";
    }

    if (!$hasTitle) {
        DB::statement("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NOT NULL AFTER type");
        echo "Agregada columna title.\n";
    }

    if (!$hasMessage) {
        DB::statement("ALTER TABLE notifications ADD COLUMN message TEXT NOT NULL AFTER title");
        echo "Agregada columna message.\n";
    }

    if (!$hasRead) {
        DB::statement("ALTER TABLE notifications ADD COLUMN `read` BOOLEAN DEFAULT FALSE AFTER data");
        echo "Agregada columna read.\n";
    }

    echo "Tablas verificadas y actualizadas exitosamente.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
