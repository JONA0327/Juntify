<?php
// Script para crear la base de datos local nueva
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS juntify_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Base de datos juntify_new creada exitosamente\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
