<?php
// Script para analizar y limpiar datos irrelevantes de la BD
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');

    echo "📊 ANÁLISIS DE TABLAS Y DATOS IRRELEVANTES\n";
    echo "==========================================\n\n";

    // Tablas que probablemente contengan datos temporales o irrelevantes
    $potentialCleanup = [
        'notifications' => 'Notificaciones (pueden ser temporales)',
        'transcription_temps' => 'Transcripciones temporales',
        'pending_recordings' => 'Grabaciones pendientes',
        'password_reset_tokens' => 'Tokens de reset de contraseña',
        'ai_daily_usage' => 'Estadísticas diarias de IA',
        'monthly_meeting_usage' => 'Estadísticas mensuales',
        'organization_activities' => 'Actividades de organización',
        'migrations' => 'Registro de migraciones (Laravel)',
        'analyzers' => 'Analizadores (posiblemente temporales)',
        'limits' => 'Límites (posiblemente vacía)',
        'pending_folders' => 'Carpetas pendientes'
    ];

    $totalSavings = 0;
    $tablesToClean = [];

    foreach ($potentialCleanup as $table => $description) {
        try {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                echo "⚠️  Tabla '$table' no existe\n";
                continue;
            }

            // Contar registros
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];

            if ($count > 0) {
                // Estimar tamaño aproximado
                $stmt = $pdo->query("SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                                   FROM information_schema.TABLES
                                   WHERE table_schema = 'juntify_new' AND table_name = '$table'");
                $sizeResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $sizeKB = $sizeResult['size_kb'] ?? 0;

                echo "🔍 $table: $count registros (~{$sizeKB}KB) - $description\n";
                $tablesToClean[] = ['table' => $table, 'count' => $count, 'size' => $sizeKB, 'desc' => $description];
                $totalSavings += $sizeKB;
            } else {
                echo "✅ $table: vacía\n";
            }
        } catch (Exception $e) {
            echo "❌ Error con tabla $table: " . $e->getMessage() . "\n";
        }
    }

    echo "\n💾 POTENCIAL AHORRO TOTAL: ~{$totalSavings}KB\n\n";

    if (empty($tablesToClean)) {
        echo "✨ No hay tablas con datos irrelevantes para limpiar\n";
        exit(0);
    }

    echo "🧹 OPCIONES DE LIMPIEZA:\n";
    echo "========================\n";
    echo "1. 🗑️  Limpiar TODAS las tablas listadas arriba\n";
    echo "2. 🎯 Limpiar solo tablas temporales/estadísticas\n";
    echo "3. 🔧 Limpiar tablas específicas (selección manual)\n";
    echo "4. 📊 Solo mostrar análisis (no hacer cambios)\n";
    echo "5. 🛑 Salir\n\n";

    echo "Seleccione una opción (1-5): ";
    $handle = fopen("php://stdin", "r");
    $option = trim(fgets($handle));
    fclose($handle);

    $cleanedTables = [];

    switch ($option) {
        case '1':
            echo "\n🗑️  Limpiando TODAS las tablas irrelevantes...\n";
            foreach ($tablesToClean as $tableInfo) {
                $table = $tableInfo['table'];
                try {
                    $pdo->exec("DELETE FROM `$table`");
                    echo "✅ $table: {$tableInfo['count']} registros eliminados (~{$tableInfo['size']}KB)\n";
                    $cleanedTables[] = $table;
                } catch (Exception $e) {
                    echo "❌ Error limpiando $table: " . $e->getMessage() . "\n";
                }
            }
            break;

        case '2':
            echo "\n🎯 Limpiando solo tablas temporales/estadísticas...\n";
            $tempTables = ['transcription_temps', 'pending_recordings', 'password_reset_tokens',
                          'ai_daily_usage', 'monthly_meeting_usage', 'notifications'];
            foreach ($tablesToClean as $tableInfo) {
                if (in_array($tableInfo['table'], $tempTables)) {
                    $table = $tableInfo['table'];
                    try {
                        $pdo->exec("DELETE FROM `$table`");
                        echo "✅ $table: {$tableInfo['count']} registros eliminados (~{$tableInfo['size']}KB)\n";
                        $cleanedTables[] = $table;
                    } catch (Exception $e) {
                        echo "❌ Error limpiando $table: " . $e->getMessage() . "\n";
                    }
                }
            }
            break;

        case '3':
            echo "\n🔧 Limpieza manual - seleccione tablas:\n";
            foreach ($tablesToClean as $i => $tableInfo) {
                echo "¿Limpiar {$tableInfo['table']} ({$tableInfo['count']} registros)? (s/n): ";
                $handle = fopen("php://stdin", "r");
                $clean = trim(fgets($handle));
                fclose($handle);

                if (strtolower($clean) === 's' || strtolower($clean) === 'y') {
                    try {
                        $pdo->exec("DELETE FROM `{$tableInfo['table']}`");
                        echo "✅ {$tableInfo['table']}: eliminado\n";
                        $cleanedTables[] = $tableInfo['table'];
                    } catch (Exception $e) {
                        echo "❌ Error: " . $e->getMessage() . "\n";
                    }
                }
            }
            break;

        case '4':
            echo "\n📊 Solo análisis - no se hicieron cambios\n";
            break;

        case '5':
            echo "\n👋 Saliendo...\n";
            exit(0);

        default:
            echo "\n❌ Opción no válida\n";
            exit(1);
    }

    if (!empty($cleanedTables)) {
        echo "\n✨ RESUMEN DE LIMPIEZA:\n";
        echo "======================\n";
        foreach ($cleanedTables as $table) {
            echo "✅ $table\n";
        }

        echo "\n🔄 Regenerando archivo SQL optimizado...\n";
        echo "Ejecuta: php scripts/export_sql.php\n\n";

        // Calcular nuevo total de registros
        $totalRecords = 0;
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $totalRecords += $count;
            } catch (Exception $e) {
                // Ignorar errores de tablas que no se pueden contar
            }
        }

        echo "📊 Total de registros después de la limpieza: $totalRecords\n";
    }

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
