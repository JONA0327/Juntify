<?php
// Script para inspeccionar las tablas de empresa e integrantes
try {
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    echo "🔍 INSPECCIÓN DE TABLAS DE EMPRESA\n";
    echo "==================================\n\n";

    // Conectar a Juntify_Panels
    $pdo = new PDO(
        "mysql:host=" . $_ENV['PANELS_DB_HOST'] . ";port=" . $_ENV['PANELS_DB_PORT'] . ";dbname=" . $_ENV['PANELS_DB_DATABASE'],
        $_ENV['PANELS_DB_USERNAME'],
        $_ENV['PANELS_DB_PASSWORD']
    );

    echo "✅ Conectado a Juntify_Panels\n\n";

    // Inspeccionar tabla empresa
    echo "📋 ESTRUCTURA DE LA TABLA 'empresa':\n";
    echo "====================================\n";
    $stmt = $pdo->query("DESCRIBE empresa");
    $empresaColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empresaColumns as $column) {
        echo sprintf("%-20s %-30s %-8s %-8s %-15s %s\n",
            $column['Field'], $column['Type'], $column['Null'],
            $column['Key'], $column['Default'] ?? 'NULL', $column['Extra']
        );
    }

    echo "\n📋 ESTRUCTURA DE LA TABLA 'integrantes_empresa':\n";
    echo "==============================================\n";
    $stmt = $pdo->query("DESCRIBE integrantes_empresa");
    $integrantesColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($integrantesColumns as $column) {
        echo sprintf("%-20s %-30s %-8s %-8s %-15s %s\n",
            $column['Field'], $column['Type'], $column['Null'],
            $column['Key'], $column['Default'] ?? 'NULL', $column['Extra']
        );
    }

    // Verificar datos existentes
    echo "\n📊 DATOS ACTUALES:\n";
    echo "==================\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM empresa");
    $empresaCount = $stmt->fetchColumn();
    echo "Empresas: $empresaCount\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM integrantes_empresa");
    $integrantesCount = $stmt->fetchColumn();
    echo "Integrantes: $integrantesCount\n\n";

    if ($empresaCount > 0) {
        echo "🏢 EMPRESAS EXISTENTES:\n";
        echo "======================\n";
        $stmt = $pdo->query("SELECT * FROM empresa LIMIT 5");
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($empresas as $empresa) {
            echo "ID: {$empresa['id']}\n";
            echo "User ID: {$empresa['iduser']}\n";
            echo "Nombre: {$empresa['nombre_empresa']}\n";
            echo "Rol: {$empresa['rol']}\n";
            echo "Es Admin: " . ($empresa['es_administrador'] ? 'Sí' : 'No') . "\n";
            echo "---\n";
        }
    }

    if ($integrantesCount > 0) {
        echo "\n👥 INTEGRANTES EXISTENTES:\n";
        echo "========================\n";
        $stmt = $pdo->query("SELECT * FROM integrantes_empresa LIMIT 5");
        $integrantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($integrantes as $integrante) {
            echo "ID: {$integrante['id']}\n";
            echo "Empresa ID: {$integrante['empresa_id']}\n";
            echo "User ID: {$integrante['iduser']}\n";
            if (isset($integrante['rol'])) echo "Rol: {$integrante['rol']}\n";
            if (isset($integrante['permisos'])) echo "Permisos: {$integrante['permisos']}\n";
            if (isset($integrante['es_administrador'])) echo "Es Admin: " . ($integrante['es_administrador'] ? 'Sí' : 'No') . "\n";
            echo "---\n";
        }
    }

    echo "\n💡 ANÁLISIS:\n";
    echo "============\n";
    echo "Para auto-agregar el dueño como administrador necesitamos:\n";
    echo "1. 🔍 Identificar dónde se crean las empresas en el código\n";
    echo "2. ✅ Agregar lógica para insertar en 'integrantes_empresa'\n";
    echo "3. 🔧 Definir los permisos y rol del administrador\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
