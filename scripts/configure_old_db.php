<?php
/**
 * Script para configurar la conexión a la base de datos antigua
 * Ejecutar con: php scripts/configure_old_db.php
 */

echo "🔧 Configurando conexión a base de datos antigua...\n\n";

// Variables por defecto
$defaultConfig = [
    'OLD_LOCAL_DB_HOST' => '127.0.0.1',
    'OLD_LOCAL_DB_PORT' => '3306',
    'OLD_LOCAL_DB_DATABASE' => 'juntify_old',
    'OLD_LOCAL_DB_USERNAME' => 'root',
    'OLD_LOCAL_DB_PASSWORD' => '',
];

$envFile = __DIR__ . '/../.env';

// Leer archivo .env actual si existe
$envContent = '';
$existingVars = [];

if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $lines = explode("\n", $envContent);

    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $existingVars[trim($key)] = trim($value);
        }
    }
}

echo "📋 Configuración actual:\n";
foreach ($defaultConfig as $key => $defaultValue) {
    $currentValue = $existingVars[$key] ?? 'NO CONFIGURADA';
    echo "   $key = $currentValue\n";
}

echo "\n¿Desea actualizar la configuración? (y/n): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'y' && strtolower($response) !== 'yes') {
    echo "❌ Configuración cancelada.\n";
    exit(0);
}

echo "\n🔧 Ingrese los datos de conexión (Enter para mantener valor actual):\n\n";

$newConfig = [];
foreach ($defaultConfig as $key => $defaultValue) {
    $currentValue = $existingVars[$key] ?? $defaultValue;

    $prompt = match($key) {
        'OLD_LOCAL_DB_HOST' => 'Host de la BD antigua',
        'OLD_LOCAL_DB_PORT' => 'Puerto de la BD antigua',
        'OLD_LOCAL_DB_DATABASE' => 'Nombre de la BD antigua',
        'OLD_LOCAL_DB_USERNAME' => 'Usuario de la BD antigua',
        'OLD_LOCAL_DB_PASSWORD' => 'Contraseña de la BD antigua',
    };

    echo "$prompt [$currentValue]: ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);

    $newConfig[$key] = empty($input) ? $currentValue : $input;
}

// Actualizar archivo .env
$newEnvLines = [];
$updatedKeys = [];

if (!empty($envContent)) {
    $lines = explode("\n", $envContent);

    foreach ($lines as $line) {
        $updated = false;

        foreach ($newConfig as $key => $value) {
            if (strpos($line, $key . '=') === 0) {
                $newEnvLines[] = "$key=$value";
                $updatedKeys[] = $key;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $newEnvLines[] = $line;
        }
    }
} else {
    $newEnvLines[] = "# Configuración generada automáticamente";
}

// Agregar variables que no existían
foreach ($newConfig as $key => $value) {
    if (!in_array($key, $updatedKeys)) {
        $newEnvLines[] = "$key=$value";
    }
}

// Escribir archivo .env
$newEnvContent = implode("\n", $newEnvLines);
file_put_contents($envFile, $newEnvContent);

echo "\n✅ Configuración actualizada en .env\n";

// Probar conexión
echo "\n🔍 Probando conexión...\n";

try {
    $dsn = "mysql:host={$newConfig['OLD_LOCAL_DB_HOST']};port={$newConfig['OLD_LOCAL_DB_PORT']};dbname={$newConfig['OLD_LOCAL_DB_DATABASE']}";
    $pdo = new PDO($dsn, $newConfig['OLD_LOCAL_DB_USERNAME'], $newConfig['OLD_LOCAL_DB_PASSWORD']);

    // Probar consulta básica
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "✅ Conexión exitosa!\n";
    echo "📊 Se encontraron " . count($tables) . " tablas en la BD antigua.\n";

    if (count($tables) > 0) {
        echo "\n📋 Primeras 10 tablas encontradas:\n";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "   - $table\n";
        }
        if (count($tables) > 10) {
            echo "   ... y " . (count($tables) - 10) . " más\n";
        }
    }

    echo "\n🚀 Ahora puede ejecutar:\n";
    echo "   php artisan analyze:tables\n";
    echo "   php artisan migrate:old-data --dry-run\n";

} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    echo "\nVerifique que:\n";
    echo "1. El servidor MySQL esté ejecutándose\n";
    echo "2. La base de datos '{$newConfig['OLD_LOCAL_DB_DATABASE']}' exista\n";
    echo "3. Las credenciales sean correctas\n";
    echo "4. El host y puerto sean accesibles\n";
}

echo "\n";
