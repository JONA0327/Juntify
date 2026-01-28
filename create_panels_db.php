<?php
// Script para crear la base de datos Juntify_Panels

try {
    $host = '127.0.0.1';
    $port = '3306';
    $username = 'root';
    $password = '';

    // Conectar sin especificar base de datos
    $conn = new PDO("mysql:host=$host;port=$port", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear la base de datos
    $conn->exec("CREATE DATABASE IF NOT EXISTS `Juntify_Panels` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "✓ Base de datos 'Juntify_Panels' creada exitosamente\n";
    
    // Verificar que existe
    $stmt = $conn->query("SHOW DATABASES LIKE 'Juntify%'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nBases de datos encontradas:\n";
    foreach ($databases as $db) {
        echo "  - $db\n";
    }

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
