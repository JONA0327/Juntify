<?php
/**
 * Script simplificado para generar archivo SQL de migración
 * Ejecutar con: php scripts/export_sql.php
 */

echo "📄 GENERADOR DE ARCHIVO SQL PARA MIGRACIÓN\n";
echo "==========================================\n\n";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');
    echo "✅ Conectado a BD local (juntify_new)\n";

    // Obtener lista de tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 Encontradas " . count($tables) . " tablas\n\n";

    $filename = "juntify_migration_" . date('Y-m-d_H-i-s') . ".sql";
    echo "📝 Generando archivo: $filename\n\n";

    $file = fopen($filename, 'w');

    // Cabecera del archivo SQL
    fwrite($file, "-- =====================================================\n");
    fwrite($file, "-- MIGRACIÓN JUNTIFY - " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Base de datos: juntify (PRODUCCIÓN)\n");
    fwrite($file, "-- Generado desde: juntify_new (LOCAL)\n");
    fwrite($file, "-- =====================================================\n\n");

    fwrite($file, "-- Desactivar verificación de claves foráneas\n");
    fwrite($file, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($file, "SET AUTOCOMMIT = 0;\n");
    fwrite($file, "START TRANSACTION;\n\n");

    $totalRecords = 0;
    $tablesProcessed = 0;

    foreach ($tables as $table) {
        try {
            echo "🔄 Procesando $table... ";

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) {
                echo "⚠️  Sin datos\n";
                continue;
            }

            $recordCount = count($data);
            $totalRecords += $recordCount;
            $tablesProcessed++;

            // Escribir comentario de tabla
            fwrite($file, "-- =====================================================\n");
            fwrite($file, "-- Tabla: $table ($recordCount registros)\n");
            fwrite($file, "-- =====================================================\n\n");

            // Limpiar tabla
            fwrite($file, "-- Limpiar tabla existente\n");
            fwrite($file, "DELETE FROM `$table`;\n\n");

            // Obtener columnas
            $columns = array_keys($data[0]);
            $columnsList = '`' . implode('`, `', $columns) . '`';

            // Insertar datos en lotes de 100 registros
            $batchSize = 100;
            $batches = array_chunk($data, $batchSize);

            fwrite($file, "-- Insertar datos (en lotes)\n");

            foreach ($batches as $batchIndex => $batch) {
                $values = [];

                foreach ($batch as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $rowValues[] = $value;
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }

                fwrite($file, "INSERT INTO `$table` ($columnsList) VALUES\n");
                fwrite($file, implode(",\n", $values) . ";\n\n");
            }

            echo "✅ $recordCount registros\n";

        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            fwrite($file, "-- ERROR en tabla $table: " . $e->getMessage() . "\n\n");
        }
    }

    // Pie del archivo SQL
    fwrite($file, "\n-- =====================================================\n");
    fwrite($file, "-- FINALIZACIÓN\n");
    fwrite($file, "-- =====================================================\n\n");
    fwrite($file, "-- Reactivar verificación de claves foráneas\n");
    fwrite($file, "COMMIT;\n");
    fwrite($file, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($file, "SET AUTOCOMMIT = 1;\n\n");
    fwrite($file, "-- Migración completada exitosamente\n");
    fwrite($file, "-- Total de tablas: $tablesProcessed\n");
    fwrite($file, "-- Total de registros: $totalRecords\n");

    fclose($file);

    $fileSize = filesize($filename);

    echo "\n🎉 MIGRACIÓN GENERADA EXITOSAMENTE\n";
    echo "==================================\n";
    echo "📄 Archivo: $filename\n";
    echo "📊 Tamaño: " . number_format($fileSize / 1024, 2) . " KB\n";
    echo "🗂️  Tablas procesadas: $tablesProcessed\n";
    echo "📝 Total registros: $totalRecords\n\n";

    echo "📋 INSTRUCCIONES PARA USAR EL ARCHIVO:\n";
    echo "======================================\n";
    echo "1. 📤 Sube el archivo $filename a tu servidor de producción\n";
    echo "2. 🔗 Conéctate a tu BD de producción\n";
    echo "3. ⚡ Ejecuta: SOURCE $filename; o importa desde phpMyAdmin\n";
    echo "4. ✅ Verifica que los datos se hayan importado correctamente\n\n";

    if ($fileSize > 50 * 1024 * 1024) { // 50MB
        echo "⚠️  ATENCIÓN: Archivo grande (>" . number_format($fileSize / 1024 / 1024, 2) . "MB)\n";
        echo "   - Considera dividir en archivos más pequeños\n";
        echo "   - Verifica límites de tu servidor de producción\n\n";
    }

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
