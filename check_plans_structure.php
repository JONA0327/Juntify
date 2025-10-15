<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "=== ESTRUCTURA TABLA PLANS ===\n";
$stmt = $pdo->query('DESCRIBE plans');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== CONTENIDO TABLA PLANS ===\n";
$stmt = $pdo->query('SELECT * FROM plans');
while ($row = $stmt->fetch()) {
    print_r($row);
    echo "\n";
}
?>
