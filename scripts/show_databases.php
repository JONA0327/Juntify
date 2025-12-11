<?php
// Script para listar bases de datos disponibles en el servidor
try {
    // Cargar las variables de entorno
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    echo "🗄️  BASES DE DATOS DISPONIBLES EN EL SERVIDOR\n";
    echo "============================================\n\n";

    $host = $_ENV['PANELS_DB_HOST'] ?? $_ENV['DB_HOST'];
    $port = $_ENV['PANELS_DB_PORT'] ?? $_ENV['DB_PORT'];
    $username = $_ENV['PANELS_DB_USERNAME'] ?? $_ENV['DB_USERNAME'];
    $password = $_ENV['PANELS_DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'];

    echo "🔌 Conectando a: $host:$port\n";
    echo "👤 Usuario: $username\n\n";

    // Conectar sin especificar base de datos
    $pdo = new PDO(
        "mysql:host=$host;port=$port",
        $username,
        $password
    );

    echo "✅ Conexión exitosa!\n\n";

    // Listar todas las bases de datos
    echo "📊 Bases de datos disponibles:\n";
    echo "==============================\n";
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($databases as $db) {
        if (!in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
            echo "🗂️  $db\n";

            // Ver si alguna contiene "panel" o "juntify"
            if (stripos($db, 'panel') !== false || stripos($db, 'juntify') !== false) {
                echo "   ⭐ Posible base de datos de interés\n";
            }
        }
    }

    echo "\n💡 Sugerencias:\n";
    echo "1. Si no ves 'juntify_panels', puedes crearla\n";
    echo "2. O usar una base de datos existente que contenga datos de paneles\n";
    echo "3. Verifica si hay alguna base con nombre similar\n";

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
