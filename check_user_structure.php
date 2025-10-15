<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "=== ESTRUCTURA TABLA USERS ===\n";
$stmt = $pdo->query('DESCRIBE users');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== USUARIO GOKU ===\n";
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute(['goku03278@gmail.com']);
$user = $stmt->fetch();
if ($user) {
    print_r($user);
} else {
    echo "Usuario no encontrado\n";
}
?>
