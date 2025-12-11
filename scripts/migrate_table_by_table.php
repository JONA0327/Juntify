<?php
/**
 * Script para migrar tabla por tabla directamente a producción
 * Ejecutar con: php scripts/migrate_table_by_table.php
 */

echo "🚀 MIGRACIÓN TABLA POR TABLA A PRODUCCIÓN\n";
echo "=========================================\n\n";

// Tablas importantes en orden de prioridad
$priority_tables = [
    'users', 'permissions', 'organizations', 'groups',
    'plans', 'plan_limits', 'contacts',
    'transcriptions_laravel', 'tasks_laravel',
    'conversations', 'conversation_messages',
    'google_tokens', 'folders', 'subfolders'
];

try {
    $local = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');

    echo "⚠️  ATENCIÓN: Necesitas configurar la conexión de producción\n";
    echo "Edita este archivo y agrega las credenciales de producción\n\n";

    // $prod = new PDO('mysql:host=82.197.93.18;dbname=juntify', 'root', 'Jona@0327801');

    echo "🔍 Analizando tablas locales...\n\n";

    $stmt = $local->query("SHOW TABLES");
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 PLAN DE MIGRACIÓN:\n";
    echo "====================\n";

    // Tablas prioritarias
    echo "🔥 PRIORIDAD ALTA:\n";
    foreach ($priority_tables as $table) {
        if (in_array($table, $all_tables)) {
            $count = $local->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  ✅ $table ($count registros)\n";
        } else {
            echo "  ❌ $table (no existe)\n";
        }
    }

    // Tablas restantes
    echo "\n📊 OTRAS TABLAS:\n";
    $other_tables = array_diff($all_tables, $priority_tables);
    foreach ($other_tables as $table) {
        $count = $local->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        if ($count > 0) {
            echo "  📄 $table ($count registros)\n";
        } else {
            echo "  ⚪ $table (vacía)\n";
        }
    }

    echo "\n💡 RECOMENDACIONES:\n";
    echo "==================\n";
    echo "1. 🎯 Usa el archivo SQL generado (más seguro)\n";
    echo "2. 📤 Si el archivo es muy grande, divídelo con split_sql.php\n";
    echo "3. 🔗 Para migración directa, descomenta la línea de conexión\n";
    echo "4. ⚡ Ejecuta migración tabla por tabla si hay problemas\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
