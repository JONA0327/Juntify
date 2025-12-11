<?php
// Script para verificar tablas en BD nueva
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify', 'root', '');
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tablas en BD Nueva: " . count($tables) . "\n\n";

    $conversationTables = array_filter($tables, fn($t) => strpos($t, 'conversation') !== false);

    echo "Tablas relacionadas con 'conversation':\n";
    if (empty($conversationTables)) {
        echo "  - NINGUNA ENCONTRADA\n";
    } else {
        foreach ($conversationTables as $table) {
            echo "  - $table\n";
        }
    }

    echo "\nVerificación específica:\n";
    echo "- conversations: " . (in_array('conversations', $tables) ? 'EXISTS' : 'NOT EXISTS') . "\n";
    echo "- conversation_messages: " . (in_array('conversation_messages', $tables) ? 'EXISTS' : 'NOT EXISTS') . "\n";

    echo "\nPrimeras 10 tablas encontradas:\n";
    foreach (array_slice($tables, 0, 10) as $table) {
        echo "  - $table\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
