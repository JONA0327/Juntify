<?php
/**
 * Script para migrar datos directamente de BD local a BD de producción
 * Ejecutar con: php scripts/migrate_to_production.php
 */

echo "🚀 MIGRACIÓN DIRECTA A PRODUCCIÓN\n";
echo "=================================\n\n";

// Configuraciones de BD
$localConfig = [
    'host' => '127.0.0.1',
    'database' => 'juntify_new',
    'username' => 'root',
    'password' => ''
];

$productionConfig = [
    'host' => '82.197.93.18',
    'database' => 'juntify',
    'username' => 'root',
    'password' => 'Jona@0327801'
];

try {
    // Conectar a BD local
    echo "🔗 Conectando a BD local...\n";
    $localPdo = new PDO(
        "mysql:host={$localConfig['host']};dbname={$localConfig['database']}",
        $localConfig['username'],
        $localConfig['password']
    );

    // Conectar a BD de producción
    echo "🔗 Conectando a BD de producción...\n";
    $prodPdo = new PDO(
        "mysql:host={$productionConfig['host']};dbname={$productionConfig['database']}",
        $productionConfig['username'],
        $productionConfig['password']
    );

    echo "✅ Conexiones establecidas exitosamente\n\n";

    // Obtener lista de tablas de BD local
    $stmt = $localPdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 Tablas encontradas: " . count($tables) . "\n";

    // Menú de opciones
    echo "\n📋 OPCIONES:\n";
    echo "1. 🔍 Verificar estructura de tablas\n";
    echo "2. 📊 Comparar datos entre BD\n";
    echo "3. 🔄 Migrar tabla específica\n";
    echo "4. 🚀 Migrar todas las tablas\n";
    echo "5. 📄 Generar script SQL\n";
    echo "6. 🛑 Salir\n\n";

    echo "Seleccione una opción (1-6): ";
    $handle = fopen("php://stdin", "r");
    $option = trim(fgets($handle));
    fclose($handle);

    switch ($option) {
        case '1':
            verifyStructure($localPdo, $prodPdo, $tables);
            break;
        case '2':
            compareData($localPdo, $prodPdo, $tables);
            break;
        case '3':
            migrateSingleTable($localPdo, $prodPdo, $tables);
            break;
        case '4':
            migrateAllTables($localPdo, $prodPdo, $tables);
            break;
        case '5':
            generateSqlScript($localPdo, $tables);
            break;
        case '6':
            echo "👋 Saliendo...\n";
            break;
        default:
            echo "❌ Opción no válida\n";
    }

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}

function verifyStructure($localPdo, $prodPdo, $tables) {
    echo "\n🔍 VERIFICANDO ESTRUCTURA DE TABLAS\n";
    echo "===================================\n";

    foreach ($tables as $table) {
        echo "Verificando $table... ";

        try {
            // Verificar si existe en producción
            $stmt = $prodPdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                echo "❌ NO EXISTE en producción\n";
                continue;
            }

            // Comparar estructura
            $localStruct = $localPdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            $prodStruct = $prodPdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);

            if (count($localStruct) === count($prodStruct)) {
                echo "✅ Estructura coincide\n";
            } else {
                echo "⚠️  Diferencias en estructura\n";
            }

        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

function compareData($localPdo, $prodPdo, $tables) {
    echo "\n📊 COMPARANDO DATOS ENTRE BD\n";
    echo "============================\n";
    printf("%-30s %10s %10s %10s\n", 'Tabla', 'Local', 'Producción', 'Diferencia');
    echo str_repeat('-', 62) . "\n";

    foreach ($tables as $table) {
        try {
            $localCount = $localPdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();

            try {
                $prodCount = $prodPdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            } catch (Exception $e) {
                $prodCount = 'N/A';
            }

            $diff = is_numeric($prodCount) ? ($localCount - $prodCount) : 'N/A';
            printf("%-30s %10s %10s %10s\n", $table, $localCount, $prodCount, $diff);

        } catch (Exception $e) {
            printf("%-30s %10s %10s %10s\n", $table, 'Error', 'Error', 'Error');
        }
    }
}

function migrateSingleTable($localPdo, $prodPdo, $tables) {
    echo "\n📋 TABLAS DISPONIBLES:\n";
    foreach ($tables as $i => $table) {
        echo ($i + 1) . ". $table\n";
    }

    echo "\nSeleccione el número de tabla: ";
    $handle = fopen("php://stdin", "r");
    $tableIndex = (int)trim(fgets($handle)) - 1;
    fclose($handle);

    if (!isset($tables[$tableIndex])) {
        echo "❌ Selección inválida\n";
        return;
    }

    $table = $tables[$tableIndex];
    migrateTable($localPdo, $prodPdo, $table);
}

function migrateAllTables($localPdo, $prodPdo, $tables) {
    echo "\n🚀 MIGRANDO TODAS LAS TABLAS\n";
    echo "============================\n";

    $success = 0;
    $errors = 0;

    foreach ($tables as $table) {
        if (migrateTable($localPdo, $prodPdo, $table)) {
            $success++;
        } else {
            $errors++;
        }
    }

    echo "\n📊 RESUMEN:\n";
    echo "✅ Exitosas: $success\n";
    echo "❌ Errores: $errors\n";
}

function migrateTable($localPdo, $prodPdo, $table) {
    try {
        echo "🔄 Migrando $table... ";

        // Obtener datos de tabla local
        $stmt = $localPdo->query("SELECT * FROM $table");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            echo "⚠️  Sin datos\n";
            return true;
        }

        // Limpiar tabla de producción
        $prodPdo->exec("DELETE FROM $table");

        // Preparar inserción
        $columns = array_keys($data[0]);
        $placeholders = ':' . implode(', :', $columns);
        $columnsList = '`' . implode('`, `', $columns) . '`';

        $sql = "INSERT INTO $table ($columnsList) VALUES ($placeholders)";
        $stmt = $prodPdo->prepare($sql);

        // Insertar datos
        $inserted = 0;
        foreach ($data as $row) {
            if ($stmt->execute($row)) {
                $inserted++;
            }
        }

        echo "✅ $inserted registros migrados\n";
        return true;

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        return false;
    }
}

function generateSqlScript($localPdo, $tables) {
    $filename = "migration_" . date('Y-m-d_H-i-s') . ".sql";
    $file = fopen($filename, 'w');

    fwrite($file, "-- Script de migración generado el " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Base de datos: juntify\n\n");

    fwrite($file, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

    foreach ($tables as $table) {
        try {
            $stmt = $localPdo->query("SELECT * FROM $table");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) continue;

            fwrite($file, "-- Tabla: $table\n");
            fwrite($file, "DELETE FROM `$table`;\n");

            $columns = array_keys($data[0]);
            $columnsList = '`' . implode('`, `', $columns) . '`';

            foreach ($data as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                $valuesList = implode(', ', $values);
                fwrite($file, "INSERT INTO `$table` ($columnsList) VALUES ($valuesList);\n");
            }

            fwrite($file, "\n");

        } catch (Exception $e) {
            fwrite($file, "-- Error en tabla $table: " . $e->getMessage() . "\n\n");
        }
    }

    fwrite($file, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($file);

    echo "\n📄 Script SQL generado: $filename\n";
    echo "📊 Tamaño: " . number_format(filesize($filename) / 1024, 2) . " KB\n";
}
?>
