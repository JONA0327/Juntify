<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "=== VERIFICANDO TABLA transcription_temps ===\n";
$stmt = $pdo->query('SHOW TABLES LIKE "transcription_temps"');
$result = $stmt->fetch();

if ($result) {
    echo "✅ Tabla transcription_temps EXISTE\n\n";

    echo "=== ESTRUCTURA DE LA TABLA ===\n";
    $stmt = $pdo->query('DESCRIBE transcription_temps');
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' (' . $row['Type'] . ")\n";
    }
} else {
    echo "❌ Tabla transcription_temps NO EXISTE\n";
    echo "Este es el problema del error 500.\n\n";

    echo "Buscando modelos relacionados...\n";

    // Buscar si existe el modelo
    if (file_exists(__DIR__ . '/app/Models/TranscriptionTemp.php')) {
        echo "✅ Modelo TranscriptionTemp.php EXISTE\n";
    } else {
        echo "❌ Modelo TranscriptionTemp.php NO EXISTE\n";
    }
}
?>
