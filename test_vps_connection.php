<?php

echo "=== DIAGNÓSTICO CONEXIÓN VPS: 168.231.74.126 ===\n\n";

// Configuración del servidor de producción
$config = [
    'host' => '168.231.74.126',
    'port' => 3306,
    'database' => 'juntify',
    'username' => 'cerounocero',
    'password' => 'Cerounocero.com20182417'
];

echo "🔍 PROBANDO CONEXIÓN AL SERVIDOR DE PRODUCCIÓN:\n";
echo "   Host: {$config['host']}:{$config['port']}\n";
echo "   Database: {$config['database']}\n";
echo "   Usuario: {$config['username']}\n\n";

// Test 1: Ping al servidor
echo "1. 🌐 PING AL SERVIDOR:\n";
$pingResult = shell_exec("ping -c 4 {$config['host']} 2>&1");
if ($pingResult) {
    echo "   " . trim($pingResult) . "\n";
} else {
    echo "   ❌ No se puede hacer ping\n";
}
echo "\n";

// Test 2: Verificar conectividad del puerto
echo "2. 🔌 VERIFICAR PUERTO 3306:\n";
$connection = @fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
if ($connection) {
    echo "   ✅ Puerto 3306 está abierto y accesible\n";
    fclose($connection);
} else {
    echo "   ❌ No se puede conectar al puerto 3306\n";
    echo "   Error: $errstr ($errno)\n";
}
echo "\n";

// Test 3: Intentar conexión MySQL
echo "3. 🗄️ CONEXIÓN MYSQL:\n";
try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";

    echo "   DSN: $dsn\n";
    echo "   Intentando conectar...\n";

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    echo "   ✅ CONEXIÓN EXITOSA!\n\n";

    // Test 4: Verificar base de datos
    echo "4. 📋 VERIFICAR BASE DE DATOS:\n";

    // Información del servidor
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetchColumn();
    echo "   MySQL Version: $version\n";

    // Listar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Tablas encontradas: " . count($tables) . "\n";

    // Verificar tablas específicas
    $importantTables = [
        'transcriptions_temp',
        'transcriptions_laravel',
        'tasks_laravel',
        'users'
    ];

    echo "\n   Verificando tablas importantes:\n";
    foreach ($importantTables as $table) {
        if (in_array($table, $tables)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                echo "   ✅ $table: $count registros\n";
            } catch (Exception $e) {
                echo "   ⚠️ $table: Existe pero error al contar - " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ❌ $table: No existe\n";
        }
    }

    // Test 5: Verificar usuario específico
    echo "\n5. 👤 VERIFICAR USUARIOS:\n";
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE username = ? OR id = 1 LIMIT 5");
        $stmt->execute(['JONA0327']);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($users) {
            echo "   Usuarios encontrados:\n";
            foreach ($users as $user) {
                echo "   - ID: {$user['id']} | {$user['username']} | {$user['email']}\n";
            }
        } else {
            echo "   ⚠️ No se encontraron usuarios específicos\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error al verificar usuarios: " . $e->getMessage() . "\n";
    }

    // Test 6: Verificar reuniones temporales
    echo "\n6. 📝 VERIFICAR REUNIONES TEMPORALES:\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM transcriptions_temp WHERE expires_at > NOW()");
        $activeTempMeetings = $stmt->fetchColumn();
        echo "   Reuniones temporales activas: $activeTempMeetings\n";

        if ($activeTempMeetings > 0) {
            $stmt = $pdo->query("
                SELECT id, meeting_name, created_at, expires_at
                FROM transcriptions_temp
                WHERE expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 3
            ");
            $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "   Últimas reuniones temporales:\n";
            foreach ($meetings as $meeting) {
                echo "   - ID: {$meeting['id']} | {$meeting['meeting_name']} | Expira: {$meeting['expires_at']}\n";
            }
        }
    } catch (Exception $e) {
        echo "   ❌ Error al verificar reuniones temporales: " . $e->getMessage() . "\n";
    }

    echo "\n✅ CONEXIÓN AL VPS FUNCIONANDO CORRECTAMENTE!\n";

} catch (PDOException $e) {
    echo "   ❌ ERROR DE CONEXIÓN MYSQL:\n";
    echo "   Código: " . $e->getCode() . "\n";
    echo "   Mensaje: " . $e->getMessage() . "\n\n";

    echo "🛠️ POSIBLES SOLUCIONES:\n\n";

    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "1. CREDENCIALES INCORRECTAS:\n";
        echo "   - Verificar usuario: {$config['username']}\n";
        echo "   - Verificar contraseña\n";
        echo "   - Comprobar permisos del usuario en MySQL\n\n";

        echo "   Comandos en el VPS:\n";
        echo "   mysql -u root -p\n";
        echo "   SHOW DATABASES;\n";
        echo "   SELECT User, Host FROM mysql.user WHERE User='cerounocero';\n";
        echo "   GRANT ALL PRIVILEGES ON juntify.* TO 'cerounocero'@'%';\n";
        echo "   FLUSH PRIVILEGES;\n\n";
    }

    if (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'timed out') !== false) {
        echo "2. PROBLEMA DE CONEXIÓN DE RED:\n";
        echo "   - MySQL puede no estar escuchando en IP externa\n";
        echo "   - Firewall bloqueando puerto 3306\n";
        echo "   - Configuración bind-address incorrecta\n\n";

        echo "   Comandos en el VPS:\n";
        echo "   sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf\n";
        echo "   (Cambiar bind-address = 127.0.0.1 por bind-address = 0.0.0.0)\n";
        echo "   sudo systemctl restart mysql\n";
        echo "   sudo ufw allow 3306\n\n";
    }

    if (strpos($e->getMessage(), "Can't connect") !== false) {
        echo "3. SERVICIO MYSQL NO ACTIVO:\n";
        echo "   Comandos en el VPS:\n";
        echo "   sudo systemctl status mysql\n";
        echo "   sudo systemctl start mysql\n";
        echo "   sudo systemctl enable mysql\n\n";
    }

    echo "4. VERIFICACIONES GENERALES EN EL VPS:\n";
    echo "   sudo netstat -tlnp | grep :3306\n";
    echo "   sudo tail -f /var/log/mysql/error.log\n";
    echo "   mysql -u cerounocero -p -h localhost juntify\n\n";
}

echo "\n📊 RESUMEN DEL DIAGNÓSTICO:\n";
echo "========================================\n";
echo "Servidor: {$config['host']}:{$config['port']}\n";
echo "Base de datos: {$config['database']}\n";
echo "Usuario: {$config['username']}\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

?>
