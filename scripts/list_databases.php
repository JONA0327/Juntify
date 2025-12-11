<?php
// Script para listar bases de datos disponibles
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $stmt = $pdo->query('SHOW DATABASES');
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Bases de datos disponibles:\n";
    foreach($databases as $db) {
        echo "- $db\n";
    }

    // Buscar específicamente juntify
    $juntifyDbs = array_filter($databases, fn($db) => stripos($db, 'juntify') !== false);

    echo "\nBases de datos relacionadas con 'juntify':\n";
    if (empty($juntifyDbs)) {
        echo "- NINGUNA ENCONTRADA\n";
    } else {
        foreach ($juntifyDbs as $db) {
            echo "- $db\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
