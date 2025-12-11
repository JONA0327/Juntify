<?php
// Script para verificar el tamaño de las tablas
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');
    $stmt = $pdo->query('
        SELECT
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.tables
        WHERE table_schema = "juntify_new"
        ORDER BY (data_length + index_length) DESC
    ');

    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "TAMAÑO DE TABLAS EN juntify_new:\n";
    echo "================================\n";
    printf("%-30s %10s %10s\n", 'Tabla', 'Registros', 'Tamaño(MB)');
    echo str_repeat('-', 52) . "\n";

    $totalSize = 0;
    $largestTables = [];
    foreach ($tables as $table) {
        printf("%-30s %10s %10s\n",
            $table['table_name'],
            $table['table_rows'],
            $table['size_mb']
        );
        $totalSize += $table['size_mb'];

        if ($table['size_mb'] > 0.5) {  // Tablas mayores a 0.5MB
            $largestTables[] = $table;
        }
    }

    echo str_repeat('-', 52) . "\n";
    printf("%-30s %10s %10s\n", 'TOTAL BD', '', number_format($totalSize, 2));

    // Mostrar problema potencial
    echo "\n📊 DIAGNÓSTICO:\n";
    if ($totalSize > 100) {
        echo "⚠️  BD muy grande (>{$totalSize}MB) - puede causar problemas de importación\n";
    } else {
        echo "✅ Tamaño de BD aceptable ({$totalSize}MB)\n";
    }

    if (!empty($largestTables)) {
        echo "\n📋 TABLAS MÁS GRANDES:\n";
        foreach ($largestTables as $table) {
            echo "- {$table['table_name']}: {$table['size_mb']}MB ({$table['table_rows']} registros)\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
