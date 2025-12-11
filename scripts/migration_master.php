<?php
/**
 * Script maestro para migración de datos
 * Ejecutar con: php scripts/migration_master.php
 */

echo "🚀 MIGRACIÓN JUNTIFY - SCRIPT MAESTRO\n";
echo "=====================================\n\n";

echo "Este script te guiará a través del proceso completo de migración de datos.\n";
echo "BD Origen: juntify_old (local)\n";
echo "BD Destino: juntify (producción) o juntify_new (testing local)\n\n";

// Menú principal
while (true) {
    echo "📋 OPCIONES DISPONIBLES:\n";
    echo "1. 📊 Analizar tablas disponibles\n";
    echo "2. 🧪 Configurar modo testing local (SEGURO)\n";
    echo "3. 🚀 Configurar modo producción (RIESGO)\n";
    echo "4. 🔍 Ejecutar migración en modo dry-run\n";
    echo "5. ✅ Ejecutar migración real\n";
    echo "6. 🔎 Verificar resultado de migración\n";
    echo "7. 📄 Ver documentación de migración\n";
    echo "8. 🛑 Salir\n\n";

    echo "Seleccione una opción (1-8): ";
    $handle = fopen("php://stdin", "r");
    $option = trim(fgets($handle));
    fclose($handle);

    echo "\n";

    switch ($option) {
        case '1':
            echo "🔍 Analizando tablas...\n";
            system('php artisan analyze:tables');
            break;

        case '2':
            echo "🧪 Configurando modo testing local...\n";
            system('php scripts/switch_db_config.php local');
            break;

        case '3':
            echo "🚀 Configurando modo producción...\n";
            system('php scripts/switch_db_config.php production');
            break;

        case '4':
            echo "🔍 Ejecutando migración en modo dry-run...\n";
            echo "⚠️  Esto NO modifica datos, solo muestra qué se haría.\n\n";
            system('php artisan migrate:old-data --dry-run');
            break;

        case '5':
            echo "✅ EJECUTANDO MIGRACIÓN REAL\n";
            echo "⚠️  ESTO MODIFICARÁ LA BASE DE DATOS DESTINO\n\n";

            // Verificar configuración actual
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'DB_HOST=82.197.93.18') !== false &&
                strpos($envContent, '#DB_HOST=82.197.93.18') === false) {
                echo "🚨 ATENCIÓN: Configurado para PRODUCCIÓN\n";
                echo "¿Está ABSOLUTAMENTE seguro? (escriba 'EJECUTAR PRODUCCION'): ";
            } else {
                echo "🧪 Configurado para testing local\n";
                echo "¿Confirma la ejecución? (escriba 'SI'): ";
            }

            $handle = fopen("php://stdin", "r");
            $confirm = trim(fgets($handle));
            fclose($handle);

            if ($confirm === 'EJECUTAR PRODUCCION' || $confirm === 'SI') {
                echo "\n🚀 Ejecutando migración...\n";
                system('php artisan migrate:old-data');
            } else {
                echo "❌ Migración cancelada por seguridad.\n";
            }
            break;

        case '6':
            echo "🔎 Verificando resultado de migración...\n";
            system('php artisan verify:migration');
            break;

        case '7':
            echo "📄 Mostrando documentación...\n";
            if (file_exists('MIGRATION_GUIDE.md')) {
                echo file_get_contents('MIGRATION_GUIDE.md');
            } else {
                echo "❌ Documentación no encontrada.\n";
            }
            break;

        case '8':
            echo "👋 ¡Hasta luego!\n";
            exit(0);

        default:
            echo "❌ Opción no válida. Por favor seleccione 1-8.\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Presione Enter para continuar...";
    fgets(fopen("php://stdin", "r"));
    echo "\n";
}
?>
