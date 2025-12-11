<?php
// Script para eliminar tablas específicas
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');
    echo "🗑️ Eliminando datos de tablas especificadas...\n";

    // Eliminar organization_container_folders
    $stmt = $pdo->query('SELECT COUNT(*) FROM organization_container_folders');
    $count1 = $stmt->fetchColumn();
    $pdo->exec('DELETE FROM organization_container_folders');
    echo "✅ organization_container_folders: $count1 registros eliminados\n";

    // Eliminar organization_group_folders
    $stmt = $pdo->query('SELECT COUNT(*) FROM organization_group_folders');
    $count2 = $stmt->fetchColumn();
    $pdo->exec('DELETE FROM organization_group_folders');
    echo "✅ organization_group_folders: $count2 registros eliminados\n";

    // Eliminar payments
    $stmt = $pdo->query('SELECT COUNT(*) FROM payments');
    $count3 = $stmt->fetchColumn();
    $pdo->exec('DELETE FROM payments');
    echo "✅ payments: $count3 registros eliminados\n";

    $total = $count1 + $count2 + $count3;
    echo "\n📊 Total de registros eliminados: $total\n";
    echo "🔄 Regenera el archivo con: php scripts/export_sql.php\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
