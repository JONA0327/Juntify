<?php
// Script para analizar y limpiar conversaciones
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=juntify_new', 'root', '');

    echo "ANÁLISIS DE CONVERSACIONES:\n";
    echo "===========================\n";

    // Verificar conversations
    $stmt = $pdo->query('SELECT COUNT(*) FROM conversations');
    $convCount = $stmt->fetchColumn();
    echo "Conversations: $convCount registros\n";

    // Verificar conversation_messages
    $stmt = $pdo->query('SELECT COUNT(*) FROM conversation_messages');
    $msgCount = $stmt->fetchColumn();
    echo "Conversation_messages: $msgCount registros\n";

    if ($msgCount > 0) {
        // Verificar tamaño promedio de mensajes
        $stmt = $pdo->query('SELECT AVG(LENGTH(content)) as avg_length FROM conversation_messages WHERE content IS NOT NULL');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgLength = round($result['avg_length'] ?? 0);
        echo "Longitud promedio de mensajes: $avgLength caracteres\n";

        // Verificar el mensaje más largo
        $stmt = $pdo->query('SELECT LENGTH(content) as max_length FROM conversation_messages ORDER BY LENGTH(content) DESC LIMIT 1');
        $maxLength = $stmt->fetchColumn();
        echo "Mensaje más largo: $maxLength caracteres\n";

        // Calcular tamaño total aproximado
        $totalSize = $msgCount * $avgLength;
        echo "Tamaño total aproximado: " . number_format($totalSize / 1024, 2) . " KB\n\n";
    }

    // Opciones de limpieza
    echo "OPCIONES DE LIMPIEZA:\n";
    echo "====================\n";
    echo "1. 🗑️  Borrar todos los mensajes de conversaciones\n";
    echo "2. ✂️  Truncar mensajes largos (mantener solo primeros 100 caracteres)\n";
    echo "3. 🔄 Reemplazar contenido con texto genérico\n";
    echo "4. 📊 Solo mostrar análisis (no hacer cambios)\n";
    echo "5. 🛑 Salir\n\n";

    echo "Seleccione una opción (1-5): ";
    $handle = fopen("php://stdin", "r");
    $option = trim(fgets($handle));
    fclose($handle);

    switch ($option) {
        case '1':
            echo "\n🗑️  Borrando todos los mensajes de conversaciones...\n";
            $pdo->exec('DELETE FROM conversation_messages');
            echo "✅ Mensajes borrados exitosamente\n";
            break;

        case '2':
            echo "\n✂️  Truncando mensajes largos...\n";
            $stmt = $pdo->prepare('UPDATE conversation_messages SET content = LEFT(content, 100) WHERE LENGTH(content) > 100');
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "✅ $affected mensajes truncados\n";
            break;

        case '3':
            echo "\n🔄 Reemplazando contenido con texto genérico...\n";
            $pdo->exec("UPDATE conversation_messages SET content = '[Contenido removido para migración]'");
            $affected = $pdo->lastInsertId();
            echo "✅ Contenido reemplazado\n";
            break;

        case '4':
            echo "\n📊 Solo análisis - no se hicieron cambios\n";
            break;

        case '5':
            echo "\n👋 Saliendo...\n";
            exit(0);

        default:
            echo "\n❌ Opción no válida\n";
            exit(1);
    }

    if ($option >= 1 && $option <= 3) {
        echo "\n🔄 Regenerando archivo SQL optimizado...\n";
        echo "Ejecuta: php scripts/export_sql.php\n\n";

        // Verificar nuevo estado
        $stmt = $pdo->query('SELECT COUNT(*) FROM conversation_messages');
        $newMsgCount = $stmt->fetchColumn();
        echo "📊 Estado después de la limpieza:\n";
        echo "Mensajes restantes: $newMsgCount\n";

        if ($newMsgCount > 0) {
            $stmt = $pdo->query('SELECT AVG(LENGTH(content)) as avg_length FROM conversation_messages WHERE content IS NOT NULL');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $newAvgLength = round($result['avg_length'] ?? 0);
            echo "Nueva longitud promedio: $newAvgLength caracteres\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
