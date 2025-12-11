<?php
// Script para probar la conexión a la base de datos Juntify_Panels
try {
    // Cargar las variables de entorno
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    echo "🔌 PRUEBA DE CONEXIÓN A JUNTIFY_PANELS\n";
    echo "======================================\n\n";

    // Obtener las variables de entorno
    $host = $_ENV['PANELS_DB_HOST'] ?? 'No definido';
    $port = $_ENV['PANELS_DB_PORT'] ?? 'No definido';
    $database = $_ENV['PANELS_DB_DATABASE'] ?? 'No definido';
    $username = $_ENV['PANELS_DB_USERNAME'] ?? 'No definido';

    echo "📋 Configuración:\n";
    echo "Host: $host\n";
    echo "Puerto: $port\n";
    echo "Base de datos: $database\n";
    echo "Usuario: $username\n\n";

    // Intentar conectar
    echo "🔄 Intentando conectar...\n";
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database",
        $username,
        $_ENV['PANELS_DB_PASSWORD'] ?? ''
    );

    echo "✅ Conexión exitosa!\n\n";

    // Mostrar tablas disponibles
    echo "📊 Tablas disponibles en $database:\n";
    echo "==================================\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "⚠️  No se encontraron tablas en la base de datos\n";
    } else {
        foreach ($tables as $table) {
            try {
                $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $countStmt->fetchColumn();
                echo "📁 $table: $count registros\n";
            } catch (Exception $e) {
                echo "📁 $table: Error al contar registros\n";
            }
        }
    }

    echo "\n🎉 Conexión a Juntify_Panels configurada correctamente!\n";

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    echo "\nVerifica:\n";
    echo "1. ✅ Variables de entorno en .env\n";
    echo "2. ✅ Credenciales de base de datos\n";
    echo "3. ✅ Que la base de datos 'juntify_panels' exista\n";
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}
?>
