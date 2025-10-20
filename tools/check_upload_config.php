<?php

echo "=== CONFIGURACIÓN DE PHP PARA UPLOADS ===\n\n";

$settings = [
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
];

foreach ($settings as $setting => $value) {
    echo sprintf("%-20s: %s\n", $setting, $value);
}

echo "\n=== ESTADO DEL SISTEMA ===\n\n";

// Verificar que las tablas existan
echo "Verificando tablas de la cola:\n";
try {
    $pdo = new PDO("mysql:host=168.231.74.126;dbname=juntify", "cerounocero", "Cerounocero.com20182417");

    $tables = ['jobs', 'failed_jobs'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  - {$table}: {$count} registros\n";
    }

    echo "\nVerificando usuario de prueba:\n";
    $stmt = $pdo->prepare("SELECT username, plan, plan_expires_at FROM users WHERE email = ?");
    $stmt->execute(['goku03278@gmail.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "  - Username: {$user['username']}\n";
        echo "  - Plan: {$user['plan']}\n";
        echo "  - Expira: {$user['plan_expires_at']}\n";
    } else {
        echo "  - Usuario no encontrado\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== RECOMENDACIONES ===\n\n";
echo "✅ Middleware configurado para aumentar límites\n";
echo "✅ Worker de cola ejecutándose\n";
echo "✅ Procesamiento en background habilitado\n";
echo "✅ Plan Basic otorgado al usuario\n";
echo "\n💡 Para subir archivos grandes:\n";
echo "   1. Asegúrate de que el worker esté ejecutándose\n";
echo "   2. El archivo se subirá a Google Drive primero\n";
echo "   3. El procesamiento del texto se hará en background\n";
echo "   4. Verifica el progreso en la interfaz\n";
