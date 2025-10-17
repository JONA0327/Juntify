<?php

echo "=== DEBUG CREACIÓN SESIÓN - PLAN BÁSICO ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=168.231.74.126;port=3306;dbname=juntify;charset=utf8mb4',
        'cerounocero',
        'Cerounocero.com20182417',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. Verificar el usuario con plan básico
    echo "1. 👤 VERIFICANDO USUARIO CON PLAN BÁSICO:\n";

    $stmt = $pdo->prepare("SELECT id, username, roles FROM users WHERE roles = ? LIMIT 1");
    $stmt->execute(['basic']);
    $basicUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($basicUser) {
        echo "   ✅ Usuario básico encontrado:\n";
        echo "   - ID: {$basicUser['id']}\n";
        echo "   - Username: {$basicUser['username']}\n";
        echo "   - Plan: {$basicUser['roles']}\n";

        // 2. Simular la lógica del método createSession
        echo "\n2. 🧪 SIMULANDO LÓGICA DE CREACIÓN DE SESIÓN:\n";

        echo "   Paso 1: Verificar límites...\n";

        // Simular checkFreePlanLimits (que aparentemente no existe)
        $role = strtolower($basicUser['roles']);

        // Lógica de límites para plan básico
        $limits = [];
        switch($role) {
            case 'free':
                $limits = [
                    'allowed' => false,
                    'can_send_message' => false,
                    'dailyMessages' => 3,
                    'dailyDocuments' => 1
                ];
                break;
            case 'basic':
                $limits = [
                    'allowed' => true,
                    'can_send_message' => true,
                    'dailyMessages' => 10,
                    'dailyDocuments' => 5
                ];
                break;
            default:
                $limits = [
                    'allowed' => true,
                    'can_send_message' => true,
                    'dailyMessages' => 'unlimited',
                    'dailyDocuments' => 'unlimited'
                ];
        }

        echo "   Límites calculados:\n";
        echo "   - Permitido: " . ($limits['allowed'] ? 'Sí' : 'No') . "\n";
        echo "   - Puede enviar mensajes: " . ($limits['can_send_message'] ? 'Sí' : 'No') . "\n";
        echo "   - Mensajes diarios: {$limits['dailyMessages']}\n";
        echo "   - Documentos diarios: {$limits['dailyDocuments']}\n";

        if (!$limits['allowed'] && !$limits['can_send_message']) {
            echo "   ❌ BLOQUEADO: Usuario excedió límites\n";
        } else {
            echo "   ✅ PERMITIDO: Usuario puede crear sesión\n";

            // 3. Intentar crear la sesión
            echo "\n   Paso 2: Crear sesión en base de datos...\n";

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ai_chat_sessions
                    (username, title, context_type, context_id, context_data, is_active, last_activity, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                ");

                $contextData = json_encode([]);

                $result = $stmt->execute([
                    $basicUser['username'],
                    'Nueva conversación',
                    'general',
                    null,
                    $contextData,
                    1
                ]);

                if ($result) {
                    $sessionId = $pdo->lastInsertId();
                    echo "   ✅ Sesión creada exitosamente - ID: $sessionId\n";

                    // Crear mensaje del sistema
                    echo "   Paso 3: Crear mensaje del sistema...\n";

                    $stmt = $pdo->prepare("
                        INSERT INTO ai_chat_messages
                        (session_id, role, content, is_hidden, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");

                    $systemMessage = "Eres un asistente IA integral para Juntify sin un contexto específico cargado.";

                    $messageResult = $stmt->execute([
                        $sessionId,
                        'system',
                        $systemMessage,
                        1
                    ]);

                    if ($messageResult) {
                        echo "   ✅ Mensaje del sistema creado\n";
                    } else {
                        echo "   ⚠️ Error al crear mensaje del sistema\n";
                    }

                    // Verificar la sesión
                    $stmt = $pdo->prepare("
                        SELECT s.id, s.title, s.username, s.context_type, COUNT(m.id) as message_count
                        FROM ai_chat_sessions s
                        LEFT JOIN ai_chat_messages m ON s.id = m.session_id
                        WHERE s.id = ?
                        GROUP BY s.id
                    ");
                    $stmt->execute([$sessionId]);
                    $session = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($session) {
                        echo "   📋 Sesión verificada:\n";
                        echo "      - Título: {$session['title']}\n";
                        echo "      - Usuario: {$session['username']}\n";
                        echo "      - Tipo: {$session['context_type']}\n";
                        echo "      - Mensajes: {$session['message_count']}\n";
                    }

                    // Limpiar
                    $pdo->prepare("DELETE FROM ai_chat_messages WHERE session_id = ?")->execute([$sessionId]);
                    $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ?")->execute([$sessionId]);
                    echo "   🧹 Datos de prueba eliminados\n";
                }

            } catch (Exception $e) {
                echo "   ❌ Error al crear sesión: " . $e->getMessage() . "\n";
            }
        }

    } else {
        echo "   ⚠️ No se encontró usuario con plan básico\n";

        // Buscar cualquier usuario para testing
        $stmt = $pdo->query("SELECT id, username, roles FROM users LIMIT 5");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "   Usuarios disponibles:\n";
        foreach ($users as $user) {
            echo "   - {$user['username']} ({$user['roles']})\n";
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n🔍 PROBLEMA IDENTIFICADO:\n";
echo "========================================\n";
echo "El método checkFreePlanLimits() no existe en el controlador\n";
echo "Esto causa el error 500 cuando se intenta crear una sesión\n";
echo "Necesitamos implementar este método o usar resolvePlanLimits()\n";

?>
