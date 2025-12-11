<?php
// Script para inspeccionar la estructura de la tabla empresa
try {
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    echo "🔍 INSPECCIÓN DE LA TABLA EMPRESA\n";
    echo "=================================\n\n";

    // Conectar a Juntify_Panels
    $pdo = new PDO(
        "mysql:host=" . $_ENV['PANELS_DB_HOST'] . ";port=" . $_ENV['PANELS_DB_PORT'] . ";dbname=" . $_ENV['PANELS_DB_DATABASE'],
        $_ENV['PANELS_DB_USERNAME'],
        $_ENV['PANELS_DB_PASSWORD']
    );

    echo "✅ Conectado a Juntify_Panels\n\n";

    // Describir la estructura de la tabla empresa
    echo "📋 ESTRUCTURA DE LA TABLA 'empresa':\n";
    echo "====================================\n";
    $stmt = $pdo->query("DESCRIBE empresa");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        $field = $column['Field'];
        $type = $column['Type'];
        $null = $column['Null'];
        $key = $column['Key'];
        $default = $column['Default'] ?? 'NULL';
        $extra = $column['Extra'];

        echo sprintf("%-20s %-20s %-8s %-8s %-15s %s\n",
            $field, $type, $null, $key, $default, $extra
        );

        // Destacar la columna 'rol' que está causando el problema
        if ($field === 'rol') {
            echo "   🚨 COLUMNA PROBLEMÁTICA: '$type'\n";
            if (preg_match('/varchar\((\d+)\)/', $type, $matches)) {
                $length = $matches[1];
                echo "   📏 Longitud máxima: $length caracteres\n";
                echo "   ❌ Valor intentado: 'administrado' (12 caracteres)\n";
                if ($length < 12) {
                    echo "   💡 PROBLEMA: El valor es más largo que el límite\n";
                }
            }
        }
    }

    echo "\n🔧 SOLUCIONES POSIBLES:\n";
    echo "=======================\n";
    echo "1. 🔄 Modificar la columna 'rol' para que sea más larga\n";
    echo "2. ✂️  Usar valores más cortos: 'admin' en lugar de 'administrado'\n";
    echo "3. 📝 Cambiar el tipo de columna a TEXT\n\n";

    // Verificar si hay datos existentes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM empresa");
    $count = $stmt->fetchColumn();
    echo "📊 Registros actuales en la tabla: $count\n\n";

    if ($count == 0) {
        echo "✅ La tabla está vacía, es seguro modificar la estructura\n";
    } else {
        echo "⚠️  La tabla tiene datos, hay que ser cuidadoso con modificaciones\n";
    }

    // Mostrar el SQL para modificar la columna
    echo "\n💻 SQL PARA MODIFICAR LA COLUMNA:\n";
    echo "=================================\n";
    echo "ALTER TABLE empresa MODIFY COLUMN rol VARCHAR(50);\n\n";

    echo "¿Ejecutar esta modificación? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 's' || strtolower($response) === 'y') {
        echo "\n🔄 Modificando columna...\n";
        $pdo->exec("ALTER TABLE empresa MODIFY COLUMN rol VARCHAR(50)");
        echo "✅ Columna 'rol' modificada exitosamente a VARCHAR(50)\n";

        // Verificar la nueva estructura
        echo "\n📋 NUEVA ESTRUCTURA:\n";
        $stmt = $pdo->query("DESCRIBE empresa");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['Field'] === 'rol') {
                echo "✅ rol: " . $column['Type'] . "\n";
            }
        }
    } else {
        echo "\n📝 Modificación cancelada. Usa valores más cortos o modifica manualmente.\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
