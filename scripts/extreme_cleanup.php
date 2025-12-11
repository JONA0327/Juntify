<?php
// Script para reducción extrema de BD (objetivo: 2KB)
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');

    echo "🎯 REDUCCIÓN EXTREMA A 2KB\n";
    echo "=========================\n\n";

    // Obtener todas las tablas con datos
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tableStats = [];
    $totalRecords = 0;
    $totalSizeKB = 0;

    foreach ($tables as $table) {
        try {
            // Contar registros
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Obtener tamaño aproximado
                $stmt = $pdo->query("SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                                   FROM information_schema.TABLES
                                   WHERE table_schema = 'juntify_new' AND table_name = '$table'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $sizeKB = $result['size_kb'] ?? 0;

                $tableStats[] = [
                    'table' => $table,
                    'records' => $count,
                    'size_kb' => $sizeKB
                ];

                $totalRecords += $count;
                $totalSizeKB += $sizeKB;
            }
        } catch (Exception $e) {
            // Ignorar errores
        }
    }

    // Ordenar por tamaño descendente
    usort($tableStats, function($a, $b) {
        return $b['size_kb'] <=> $a['size_kb'];
    });

    echo "📊 ANÁLISIS DE TAMAÑO POR TABLA:\n";
    echo "================================\n";
    foreach ($tableStats as $stat) {
        echo sprintf("%-30s %4d registros (%6.2f KB)\n",
            $stat['table'], $stat['records'], $stat['size_kb']);
    }

    echo "\n💾 TOTAL ACTUAL: {$totalRecords} registros (~{$totalSizeKB} KB)\n";
    echo "🎯 OBJETIVO: ~2 KB (reducción del " . round((1 - 2/$totalSizeKB) * 100, 1) . "%)\n\n";

    // Estrategias de reducción extrema
    echo "🔥 ESTRATEGIAS DE REDUCCIÓN EXTREMA:\n";
    echo "====================================\n";
    echo "1. 💀 NUCLEAR - Borrar TODO excepto users, plans, organizations\n";
    echo "2. 🗡️  SEVERA - Mantener solo 5 usuarios y configuración básica\n";
    echo "3. ⚔️  MANUAL - Seleccionar tabla por tabla qué mantener\n";
    echo "4. 📊 ANÁLISIS - Ver estimación de cada estrategia\n";
    echo "5. 🛑 SALIR\n\n";

    echo "Seleccione una opción (1-5): ";
    $handle = fopen("php://stdin", "r");
    $option = trim(fgets($handle));
    fclose($handle);

    switch ($option) {
        case '1':
            echo "\n💀 MODO NUCLEAR - Manteniendo solo lo esencial...\n";
            $keepTables = ['users', 'plans', 'organizations'];
            foreach ($tableStats as $stat) {
                if (!in_array($stat['table'], $keepTables)) {
                    $pdo->exec("DELETE FROM `{$stat['table']}`");
                    echo "🗑️  {$stat['table']}: {$stat['records']} registros eliminados\n";
                }
            }

            // Reducir usuarios a solo los primeros 3
            $pdo->exec("DELETE FROM users WHERE id NOT IN (SELECT id FROM (SELECT id FROM users ORDER BY created_at LIMIT 3) as temp)");
            echo "✂️  users: reducidos a 3 usuarios\n";
            break;

        case '2':
            echo "\n🗡️  MODO SEVERO - Solo datos mínimos...\n";
            $keepTables = ['users', 'plans', 'organizations', 'permissions'];

            foreach ($tableStats as $stat) {
                if (!in_array($stat['table'], $keepTables)) {
                    $pdo->exec("DELETE FROM `{$stat['table']}`");
                    echo "🗑️  {$stat['table']}: eliminado\n";
                } else {
                    // Reducir registros en tablas mantenidas
                    switch($stat['table']) {
                        case 'users':
                            $pdo->exec("DELETE FROM users WHERE id NOT IN (SELECT id FROM (SELECT id FROM users ORDER BY created_at LIMIT 5) as temp)");
                            echo "✂️  users: reducidos a 5\n";
                            break;
                        case 'organizations':
                            $pdo->exec("DELETE FROM organizations WHERE id NOT IN (SELECT id FROM (SELECT id FROM organizations ORDER BY created_at LIMIT 2) as temp)");
                            echo "✂️  organizations: reducidas a 2\n";
                            break;
                    }
                }
            }
            break;

        case '3':
            echo "\n⚔️  MODO MANUAL - Selección personalizada:\n";
            $keepTables = [];
            foreach ($tableStats as $stat) {
                echo "¿Mantener {$stat['table']} ({$stat['records']} registros, {$stat['size_kb']}KB)? (s/n): ";
                $handle = fopen("php://stdin", "r");
                $keep = trim(fgets($handle));
                fclose($handle);

                if (strtolower($keep) !== 's' && strtolower($keep) !== 'y') {
                    $pdo->exec("DELETE FROM `{$stat['table']}`");
                    echo "🗑️  {$stat['table']}: eliminado\n";
                } else {
                    $keepTables[] = $stat['table'];
                    echo "✅ {$stat['table']}: mantenido\n";
                }
            }
            break;

        case '4':
            echo "\n📊 ESTIMACIONES:\n";
            echo "Modo Nuclear (~2-5 KB): Solo 3 usuarios + planes + organizaciones\n";
            echo "Modo Severo (~5-10 KB): 5 usuarios + configuración mínima\n";
            echo "Modo Manual: Depende de tu selección\n";
            exit(0);

        case '5':
            echo "\n👋 Saliendo...\n";
            exit(0);

        default:
            echo "\n❌ Opción no válida\n";
            exit(1);
    }

    // Verificar nuevo estado
    echo "\n🔄 Calculando nuevo tamaño...\n";
    $newTotal = 0;
    $newRecords = 0;

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            $newRecords += $count;

            if ($count > 0) {
                echo "✅ $table: $count registros\n";
            }
        } catch (Exception $e) {
            // Ignorar
        }
    }

    echo "\n✨ RESULTADO:\n";
    echo "Registros totales: $newRecords\n";
    echo "\n🔄 Regenera el archivo SQL con: php scripts/export_sql.php\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
