<?php
// Script para agregar al dueño actual como integrante administrador
try {
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    echo "👤 AGREGANDO DUEÑO COMO INTEGRANTE ADMINISTRADOR\n";
    echo "===============================================\n\n";

    // Conectar a Juntify_Panels
    $pdo = new PDO(
        "mysql:host=" . $_ENV['PANELS_DB_HOST'] . ";port=" . $_ENV['PANELS_DB_PORT'] . ";dbname=" . $_ENV['PANELS_DB_DATABASE'],
        $_ENV['PANELS_DB_USERNAME'],
        $_ENV['PANELS_DB_PASSWORD']
    );

    echo "✅ Conectado a Juntify_Panels\n\n";

    // Obtener la empresa existente
    $stmt = $pdo->query("SELECT * FROM empresa");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        echo "❌ No se encontró ninguna empresa existente\n";
        exit(1);
    }

    echo "🏢 EMPRESA ENCONTRADA:\n";
    echo "ID: {$empresa['id']}\n";
    echo "Usuario ID: {$empresa['iduser']}\n";
    echo "Nombre: {$empresa['nombre_empresa']}\n";
    echo "Rol: {$empresa['rol']}\n";
    echo "Es Admin: " . ($empresa['es_administrador'] ? 'Sí' : 'No') . "\n\n";

    // Verificar si ya es integrante
    $stmt = $pdo->prepare("SELECT * FROM integrantes_empresa WHERE iduser = ? AND empresa_id = ?");
    $stmt->execute([$empresa['iduser'], $empresa['id']]);
    $integranteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($integranteExistente) {
        echo "⚠️  El dueño ya es integrante de la empresa:\n";
        echo "Rol: {$integranteExistente['rol']}\n";
        echo "Permisos: " . json_encode(json_decode($integranteExistente['permisos'], true)) . "\n";
        exit(0);
    }

    // Definir permisos de administrador
    $permisosAdmin = [
        'gestionar_usuarios',
        'ver_reportes',
        'configurar_sistema',
        'gestionar_permisos',
        'acceso_total'
    ];

    echo "🔄 Agregando al dueño como integrante administrador...\n";

    // Insertar el dueño como integrante administrador
    $stmt = $pdo->prepare("
        INSERT INTO integrantes_empresa (iduser, empresa_id, rol, permisos, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");

    $resultado = $stmt->execute([
        $empresa['iduser'],
        $empresa['id'],
        'administrador',
        json_encode($permisosAdmin)
    ]);

    if ($resultado) {
        echo "✅ Dueño agregado exitosamente como integrante administrador\n\n";

        echo "📋 DETALLES DEL INTEGRANTE AGREGADO:\n";
        echo "User ID: {$empresa['iduser']}\n";
        echo "Empresa ID: {$empresa['id']}\n";
        echo "Rol: administrador\n";
        echo "Permisos: " . implode(', ', $permisosAdmin) . "\n\n";

        // Verificar el estado final
        $stmt = $pdo->query("SELECT COUNT(*) FROM integrantes_empresa WHERE empresa_id = {$empresa['id']}");
        $totalIntegrantes = $stmt->fetchColumn();
        echo "🎉 La empresa ahora tiene $totalIntegrantes integrante(s)\n";
    } else {
        echo "❌ Error al agregar el integrante: " . $pdo->errorInfo()[2] . "\n";
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
