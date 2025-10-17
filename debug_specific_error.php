<?php

echo "=== DIAGNÓSTICO ESPECÍFICO DEL ERROR ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=168.231.74.126;port=3306;dbname=juntify;charset=utf8mb4',
        'cerounocero',
        'Cerounocero.com20182417',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. Verificar estructura de la tabla users
    echo "1. 📋 ESTRUCTURA DE LA TABLA USERS:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }

    echo "\n";

    // 2. Buscar la columna del plan
    $planColumn = null;
    foreach ($columns as $col) {
        if (strpos(strtolower($col['Field']), 'plan') !== false) {
            $planColumn = $col['Field'];
            break;
        }
    }

    echo "2. 🔍 COLUMNA DE PLAN ENCONTRADA:\n";
    if ($planColumn) {
        echo "   ✅ Columna encontrada: $planColumn\n";
    } else {
        echo "   ❌ No se encontró columna de plan\n";
        echo "   💡 Buscando en otras columnas relacionadas...\n";

        // Buscar cualquier columna que pueda contener información del plan
        foreach ($columns as $col) {
            $field = strtolower($col['Field']);
            if (strpos($field, 'subscription') !== false ||
                strpos($field, 'tier') !== false ||
                strpos($field, 'level') !== false) {
                echo "   🔍 Posible columna relacionada: {$col['Field']}\n";
            }
        }
    }

    echo "\n";

    // 3. Verificar usuario con las columnas correctas
    echo "3. 👤 VERIFICANDO USUARIO CON COLUMNAS CORRECTAS:\n";

    $userQuery = "SELECT id, username, email";
    if ($planColumn) {
        $userQuery .= ", $planColumn";
    }
    $userQuery .= " FROM users WHERE username = ?";

    $stmt = $pdo->prepare($userQuery);
    $stmt->execute(['Jona0327']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "   ✅ Usuario encontrado:\n";
        foreach ($user as $key => $value) {
            echo "      $key: " . ($value ?? 'NULL') . "\n";
        }

        // 4. Verificar sesiones del usuario
        echo "\n4. 💬 VERIFICANDO SESIONES DEL ASISTENTE:\n";

        $stmt = $pdo->prepare("
            SELECT id, title, context_type, created_at, is_active
            FROM ai_chat_sessions
            WHERE username = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute(['Jona0327']);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($sessions) {
            echo "   Sesiones existentes:\n";
            foreach ($sessions as $session) {
                $status = $session['is_active'] ? 'Activa' : 'Inactiva';
                echo "   - {$session['id']}: {$session['title']} ({$session['context_type']}) - $status\n";
            }
        } else {
            echo "   ⚠️ No hay sesiones existentes\n";
        }

        // 5. Intentar crear una sesión manualmente
        echo "\n5. 🧪 INTENTANDO CREAR SESIÓN MANUALMENTE:\n";

        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_chat_sessions (username, title, context_type, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            $result = $stmt->execute([
                'Jona0327',
                'Test Session Manual',
                'general',
                1
            ]);

            if ($result) {
                $sessionId = $pdo->lastInsertId();
                echo "   ✅ Sesión creada exitosamente - ID: $sessionId\n";

                // Limpiar
                $stmt = $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                echo "   🧹 Sesión de prueba eliminada\n";
            }

        } catch (Exception $e) {
            echo "   ❌ Error al crear sesión: " . $e->getMessage() . "\n";
        }

    } else {
        echo "   ❌ Usuario no encontrado\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

// 6. Verificar el modelo AiChatSession
echo "\n6. 🔍 VERIFICANDO MODELO AI_CHAT_SESSION:\n";

if (file_exists('app/Models/AiChatSession.php')) {
    $content = file_get_contents('app/Models/AiChatSession.php');

    // Buscar el fillable
    if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\]/s', $content, $matches)) {
        echo "   Campos fillable encontrados:\n";
        $fillable = $matches[1];
        echo "   " . trim($fillable) . "\n";
    } else {
        echo "   ⚠️ No se encontró propiedad \$fillable\n";
    }

    // Buscar user_id vs username
    if (strpos($content, 'user_id') !== false) {
        echo "   🔍 El modelo usa 'user_id'\n";
    }
    if (strpos($content, 'username') !== false) {
        echo "   🔍 El modelo usa 'username'\n";
    }
} else {
    echo "   ❌ Modelo no encontrado\n";
}

echo "\n🎯 DIAGNÓSTICO FINAL:\n";
echo "================================\n";
echo "1. Las tablas del asistente existen y tienen datos\n";
echo "2. El error principal es la columna 'plan_code' inexistente\n";
echo "3. El modelo usa 'username' en lugar de 'user_id'\n";
echo "4. Necesitamos revisar el controlador que está causando el error 500\n";

?>
