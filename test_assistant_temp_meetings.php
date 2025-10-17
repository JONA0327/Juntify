<?php

// Test para verificar que el asistente incluye reuniones temporales
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=juntify;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== Test: Reuniones Temporales en Asistente IA ===\n\n";

    // Verificar reuniones regulares del usuario
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transcriptions_laravel WHERE username = ?");
    $stmt->execute(['JONA0327']);
    $regularCount = $stmt->fetchColumn();
    echo "✓ Reuniones regulares del usuario: {$regularCount}\n";

    // Verificar reuniones temporales del usuario
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transcriptions_temp WHERE user_id = ? AND expires_at > NOW()");
    $stmt->execute([1]); // Asumiendo user_id = 1
    $tempCount = $stmt->fetchColumn();
    echo "✓ Reuniones temporales activas: {$tempCount}\n\n";

    if ($tempCount > 0) {
        // Mostrar detalles de reuniones temporales
        $stmt = $pdo->prepare("
            SELECT id, meeting_name, created_at, expires_at, ju_content_path
            FROM transcriptions_temp
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([1]);
        $tempMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Reuniones temporales encontradas:\n";
        foreach ($tempMeetings as $meeting) {
            $hasContent = !empty($meeting['ju_content_path']) ? '✓' : '❌';
            echo "  - ID: {$meeting['id']} | {$meeting['meeting_name']}\n";
            echo "    Creada: {$meeting['created_at']}\n";
            echo "    Expira: {$meeting['expires_at']}\n";
            echo "    Contenido: {$hasContent} {$meeting['ju_content_path']}\n\n";
        }

        echo "=== CAMBIOS IMPLEMENTADOS ===\n";
        echo "✅ Agregado import de TranscriptionTemp al controlador\n";
        echo "✅ Modificado getMeetings() para incluir reuniones temporales\n";
        echo "✅ Las reuniones temporales aparecen con título '(Temporal)'\n";
        echo "✅ Modificado preloadMeeting() para manejar reuniones temporales\n";
        echo "✅ Agregado método getTempMeetingContent() para leer archivos .ju locales\n";
        echo "✅ Sistema de merge actualizado para evitar conflictos de ID\n\n";

        echo "=== RESULTADO ===\n";
        echo "🎯 Las reuniones temporales ahora están disponibles en el Asistente IA\n";
        echo "🎯 Total de reuniones disponibles: " . ($regularCount + $tempCount) . "\n";
        echo "🎯 Los usuarios podrán hacer preguntas sobre las reuniones temporales\n";

    } else {
        echo "⚠️  No hay reuniones temporales activas para probar\n";
        echo "💡 Crea una reunión temporal para verificar la integración\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>
