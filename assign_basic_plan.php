<?php

echo "=== ASIGNACIÓN PLAN BÁSICO - 1 DÍA ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=168.231.74.126;port=3306;dbname=juntify;charset=utf8mb4',
        'cerounocero',
        'Cerounocero.com20182417',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. Buscar tu usuario
    echo "1. 🔍 BUSCANDO USUARIO JONA0327:\n";

    $stmt = $pdo->prepare("SELECT id, username, roles, plan_expires_at FROM users WHERE username = ?");
    $stmt->execute(['Jona0327']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "   ✅ Usuario encontrado:\n";
        echo "   - ID: {$user['id']}\n";
        echo "   - Username: {$user['username']}\n";
        echo "   - Plan actual: {$user['roles']}\n";
        echo "   - Expira: " . ($user['plan_expires_at'] ?? 'Sin fecha') . "\n\n";

        // 2. Calcular nueva fecha de expiración (1 día desde ahora)
        $newExpiration = date('Y-m-d H:i:s', strtotime('+1 day'));

        echo "2. 📅 ASIGNANDO PLAN BÁSICO POR 1 DÍA:\n";
        echo "   - Nuevo plan: basic\n";
        echo "   - Válido hasta: $newExpiration\n\n";

        // 3. Actualizar el usuario
        $stmt = $pdo->prepare("
            UPDATE users
            SET roles = ?, plan_expires_at = ?
            WHERE username = ?
        ");

        $result = $stmt->execute(['basic', $newExpiration, 'Jona0327']);

        if ($result) {
            echo "3. ✅ PLAN BÁSICO ASIGNADO EXITOSAMENTE!\n\n";

            // Verificar la actualización
            $stmt = $pdo->prepare("SELECT username, roles, plan_expires_at FROM users WHERE username = ?");
            $stmt->execute(['Jona0327']);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedUser) {
                echo "   📋 Verificación:\n";
                echo "   - Usuario: {$updatedUser['username']}\n";
                echo "   - Plan: {$updatedUser['roles']}\n";
                echo "   - Expira: {$updatedUser['plan_expires_at']}\n\n";

                // Mostrar beneficios del plan básico
                echo "🎉 BENEFICIOS DEL PLAN BÁSICO ACTIVADOS:\n";
                echo "================================\n";
                echo "✅ Asistente IA: 10 consultas por día\n";
                echo "✅ Documentos IA: 5 documentos por día\n";
                echo "✅ Reuniones temporales: Acceso completo\n";
                echo "✅ Generación de tareas: Automática\n";
                echo "✅ Gestión de tareas: Acceso completo\n";
                echo "✅ Transcripciones: Sin límites\n";
                echo "✅ Análisis de reuniones: Completo\n\n";

                echo "⏰ DURACIÓN: 24 horas desde ahora\n";
                echo "📅 Expira: $newExpiration\n\n";

                echo "💡 RECORDATORIO:\n";
                echo "- Recarga la página del asistente IA\n";
                echo "- Ya no verás el modal de tareas bloqueadas\n";
                echo "- Puedes crear hasta 10 conversaciones por día\n";
                echo "- Todas las funciones están desbloqueadas\n";

            } else {
                echo "   ❌ Error al verificar la actualización\n";
            }

        } else {
            echo "   ❌ Error al actualizar el plan\n";
        }

    } else {
        echo "   ❌ Usuario Jona0327 no encontrado\n";

        // Buscar usuarios similares
        $stmt = $pdo->prepare("SELECT username, roles FROM users WHERE username LIKE ? LIMIT 5");
        $stmt->execute(['%jona%']);
        $similar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($similar) {
            echo "   🔍 Usuarios similares encontrados:\n";
            foreach ($similar as $u) {
                echo "   - {$u['username']} ({$u['roles']})\n";
            }
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n🚀 ¡LISTO PARA USAR!\n";
echo "===================\n";
echo "Tu plan básico está activo por las próximas 24 horas.\n";
echo "Disfruta de todas las funciones premium de Juntify! 🎊\n";

?>
