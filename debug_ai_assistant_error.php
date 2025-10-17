<?php

// Diagnóstico del error 500 en el asistente IA
echo "=== DIAGNÓSTICO ERROR 500 - ASISTENTE IA ===\n\n";

try {
    // Test 1: Verificar conexión básica
    echo "1. 🔌 VERIFICANDO CONEXIÓN A LA BASE DE DATOS:\n";

    $pdo = new PDO(
        'mysql:host=168.231.74.126;port=3306;dbname=juntify;charset=utf8mb4',
        'cerounocero',
        'Cerounocero.com20182417',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "   ✅ Conexión exitosa\n\n";

    // Test 2: Verificar tablas del asistente IA
    echo "2. 📋 VERIFICANDO TABLAS DEL ASISTENTE IA:\n";

    $aiTables = [
        'ai_chat_sessions' => 'Sesiones de chat del asistente',
        'ai_chat_messages' => 'Mensajes del asistente',
        'ai_documents' => 'Documentos del asistente',
        'ai_meeting_documents' => 'Documentos de reuniones',
        'users' => 'Usuarios del sistema'
    ];

    foreach ($aiTables as $table => $description) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetch();

            if ($exists) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                echo "   ✅ $description ($table): $count registros\n";

                // Mostrar estructura de tablas clave
                if ($table === 'ai_chat_sessions') {
                    $stmt = $pdo->query("DESCRIBE `$table`");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo "      Columnas: " . implode(', ', $columns) . "\n";
                }
            } else {
                echo "   ❌ $description ($table): TABLA NO EXISTE\n";
            }
        } catch (Exception $e) {
            echo "   ⚠️ $description ($table): Error - " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // Test 3: Verificar usuario específico
    echo "3. 👤 VERIFICANDO USUARIO JONA0327:\n";

    $stmt = $pdo->prepare("SELECT id, username, email, plan_code FROM users WHERE username = ?");
    $stmt->execute(['Jona0327']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "   ✅ Usuario encontrado:\n";
        echo "      ID: {$user['id']}\n";
        echo "      Username: {$user['username']}\n";
        echo "      Email: {$user['email']}\n";
        echo "      Plan: " . ($user['plan_code'] ?? 'No definido') . "\n";

        // Verificar sesiones existentes del usuario
        if (in_array('ai_chat_sessions', array_column($pdo->query("SHOW TABLES")->fetchAll(), 0))) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_sessions WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $sessions = $stmt->fetchColumn();
            echo "      Sesiones IA: $sessions\n";

            if ($sessions > 0) {
                $stmt = $pdo->prepare("
                    SELECT id, title, created_at
                    FROM ai_chat_sessions
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 3
                ");
                $stmt->execute([$user['id']]);
                $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "      Últimas sesiones:\n";
                foreach ($recentSessions as $session) {
                    echo "        - {$session['id']}: {$session['title']} ({$session['created_at']})\n";
                }
            }
        }
    } else {
        echo "   ❌ Usuario Jona0327 no encontrado\n";
    }

    echo "\n";

    // Test 4: Simular creación de sesión
    echo "4. 🧪 SIMULANDO CREACIÓN DE SESIÓN:\n";

    if ($user && in_array('ai_chat_sessions', array_column($pdo->query("SHOW TABLES")->fetchAll(), 0))) {
        try {
            // Intentar insertar una sesión de prueba
            $testTitle = "Test Session - " . date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO ai_chat_sessions (user_id, title, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");

            $success = $stmt->execute([$user['id'], $testTitle]);

            if ($success) {
                $sessionId = $pdo->lastInsertId();
                echo "   ✅ Sesión de prueba creada exitosamente\n";
                echo "      ID: $sessionId\n";
                echo "      Título: $testTitle\n";

                // Limpiar la sesión de prueba
                $stmt = $pdo->prepare("DELETE FROM ai_chat_sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                echo "      Sesión de prueba eliminada\n";
            } else {
                echo "   ❌ Error al crear sesión de prueba\n";
            }

        } catch (Exception $e) {
            echo "   ❌ Error en simulación: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ⚠️ No se puede simular - falta usuario o tabla ai_chat_sessions\n";
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ ERROR DE CONEXIÓN: " . $e->getMessage() . "\n\n";
}

// Test 5: Verificar estructura de archivos
echo "5. 📁 VERIFICANDO ARCHIVOS DEL ASISTENTE:\n";

$files = [
    'app/Http/Controllers/AiAssistantController.php' => 'Controlador principal',
    'app/Models/AiChatSession.php' => 'Modelo de sesiones',
    'app/Models/AiChatMessage.php' => 'Modelo de mensajes',
    'resources/js/ai-assistant.js' => 'JavaScript del frontend',
    'routes/web.php' => 'Rutas del sistema'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "   ✅ $description ($file): " . number_format($size) . " bytes\n";
    } else {
        echo "   ❌ $description ($file): ARCHIVO NO EXISTE\n";
    }
}

echo "\n🔧 POSIBLES CAUSAS DEL ERROR 500:\n\n";

echo "1. TABLA FALTANTE:\n";
echo "   - La tabla ai_chat_sessions puede no existir\n";
echo "   - Ejecutar: php artisan migrate\n\n";

echo "2. MODELO O RELACIÓN INCORRECTA:\n";
echo "   - Error en el modelo AiChatSession\n";
echo "   - Problema con relaciones de base de datos\n\n";

echo "3. PERMISOS DE USUARIO:\n";
echo "   - Usuario sin permisos para crear sesiones\n";
echo "   - Validación de plan fallando\n\n";

echo "4. ERROR EN EL CONTROLADOR:\n";
echo "   - Revisar logs: storage/logs/laravel.log\n";
echo "   - Verificar método store() en AiAssistantController\n\n";

echo "5. PROBLEMA DE AUTENTICACIÓN:\n";
echo "   - Usuario no autenticado correctamente\n";
echo "   - Sesión expirada\n\n";

echo "📋 COMANDOS PARA DEPURAR:\n";
echo "php artisan route:list | grep ai-assistant\n";
echo "php artisan tinker\n";
echo "tail -f storage/logs/laravel.log\n";

?>
