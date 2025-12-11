<?php
/**
 * Script para cambiar entre configuraciones de BD: testing local vs producción
 * Ejecutar con: php scripts/switch_db_config.php [local|production]
 */

if ($argc < 2) {
    echo "Uso: php scripts/switch_db_config.php [local|production]\n\n";
    echo "Configuraciones disponibles:\n";
    echo "- local: BD nueva local (juntify_new) para testing de migraciones\n";
    echo "- production: BD de producción (juntify) para ejecución real\n\n";
    exit(1);
}

$mode = strtolower($argv[1]);
$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    echo "❌ Error: Archivo .env no encontrado\n";
    exit(1);
}

$envContent = file_get_contents($envFile);

switch ($mode) {
    case 'local':
        echo "🔧 Cambiando a configuración LOCAL para testing...\n";

        // Comentar producción
        $envContent = preg_replace(
            '/^(DB_CONNECTION=mysql\nDB_HOST=82\.197\.93\.18.*?DB_PASSWORD=Jona@0327801)/m',
            '#$1',
            $envContent
        );

        // Descomentar local si está comentado
        $envContent = preg_replace(
            '/^#(DB_CONNECTION=mysql\nDB_HOST=127\.0\.0\.1.*?DB_DATABASE=juntify_new.*?DB_PASSWORD=)/m',
            '$1',
            $envContent
        );

        // Asegurar configuración local
        if (!preg_match('/DB_DATABASE=juntify_new/', $envContent)) {
            $localConfig = "\n# Base de datos LOCAL NUEVA para testing de migraciones\n";
            $localConfig .= "DB_CONNECTION=mysql\n";
            $localConfig .= "DB_HOST=127.0.0.1\n";
            $localConfig .= "DB_PORT=3306\n";
            $localConfig .= "DB_DATABASE=juntify_new\n";
            $localConfig .= "DB_USERNAME=root\n";
            $localConfig .= "DB_PASSWORD=\n";

            $envContent = str_replace("OLD_LOCAL_DB_HOST=", $localConfig . "\nOLD_LOCAL_DB_HOST=", $envContent);
        }

        echo "✅ Configuración cambiada a LOCAL (juntify_new)\n";
        echo "📊 Ahora puede ejecutar:\n";
        echo "   php artisan analyze:tables\n";
        echo "   php artisan migrate:old-data --dry-run\n";
        echo "   php artisan migrate:old-data\n";
        break;

    case 'production':
        echo "🚀 Cambiando a configuración de PRODUCCIÓN...\n";
        echo "⚠️  ATENCIÓN: Esto conectará a la BD de producción real!\n\n";
        echo "¿Está seguro? (escriba 'SI ESTOY SEGURO' para continuar): ";

        $handle = fopen("php://stdin", "r");
        $confirm = trim(fgets($handle));
        fclose($handle);

        if ($confirm !== 'SI ESTOY SEGURO') {
            echo "❌ Operación cancelada por seguridad.\n";
            exit(0);
        }

        // Comentar local
        $envContent = preg_replace(
            '/^(# Base de datos LOCAL NUEVA para testing de migraciones\nDB_CONNECTION=mysql\nDB_HOST=127\.0\.0\.1.*?DB_PASSWORD=)/m',
            '#$1',
            $envContent
        );

        // Descomentar producción
        $envContent = preg_replace(
            '/^#(Base de datos de Producción \(RESPALDO\)\n#DB_CONNECTION=mysql\n#DB_HOST=82\.197\.93\.18.*?#DB_PASSWORD=Jona@0327801)/m',
            'Base de datos de Producción (ACTIVO)\n$1',
            $envContent
        );

        $envContent = str_replace(
            '#DB_CONNECTION=mysql
#DB_HOST=82.197.93.18
#DB_PORT=3306
#DB_DATABASE=juntify
#DB_USERNAME=root
#DB_PASSWORD=Jona@0327801',
            'DB_CONNECTION=mysql
DB_HOST=82.197.93.18
DB_PORT=3306
DB_DATABASE=juntify
DB_USERNAME=root
DB_PASSWORD=Jona@0327801',
            $envContent
        );

        echo "✅ Configuración cambiada a PRODUCCIÓN\n";
        echo "⚠️  USAR CON EXTREMA PRECAUCIÓN\n";
        echo "📊 Comandos recomendados:\n";
        echo "   php artisan migrate:old-data --dry-run  # SIEMPRE primero!\n";
        echo "   php artisan migrate:old-data            # Solo después del dry-run\n";
        break;

    default:
        echo "❌ Error: Modo '$mode' no reconocido.\n";
        echo "Modos disponibles: local, production\n";
        exit(1);
}

// Escribir archivo actualizado
file_put_contents($envFile, $envContent);

// Limpiar cache
echo "\n🔄 Limpiando cache de configuración...\n";
shell_exec('cd ' . dirname(__DIR__) . ' && php artisan config:clear');

echo "🎯 Configuración aplicada exitosamente!\n\n";

// Mostrar estado actual
echo "📋 ESTADO ACTUAL:\n";
try {
    $config = parse_ini_string(str_replace('#', ';', $envContent));
    $dbHost = $config['DB_HOST'] ?? 'No configurado';
    $dbName = $config['DB_DATABASE'] ?? 'No configurado';

    echo "- Host: $dbHost\n";
    echo "- Base de datos: $dbName\n";

    if ($dbHost === '127.0.0.1') {
        echo "- Modo: 🏠 LOCAL (Testing seguro)\n";
    } else {
        echo "- Modo: 🚀 PRODUCCIÓN (¡CUIDADO!)\n";
    }

} catch (Exception $e) {
    echo "- Error leyendo configuración: " . $e->getMessage() . "\n";
}

echo "\n";
