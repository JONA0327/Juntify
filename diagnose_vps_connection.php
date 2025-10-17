<?php

echo "=== DIAGNÓSTICO DE CONEXIÓN VPS ===\n\n";

// Test 1: Conexión básica con diferentes hosts
$hosts = [
    'localhost' => '127.0.0.1',
    'mysql_local' => 'localhost',
    'mysql_socket' => 'localhost',
    'network' => '0.0.0.0'
];

$databases = ['juntify'];
$users = ['root', 'juntify', 'admin'];
$passwords = ['', 'root', 'password', 'juntify123'];

echo "🔍 PROBANDO CONEXIONES BÁSICAS:\n\n";

foreach ($hosts as $name => $host) {
    echo "--- Probando host: {$name} ({$host}) ---\n";

    foreach ($databases as $db) {
        foreach ($users as $user) {
            foreach ($passwords as $pass) {
                try {
                    $pdo = new PDO(
                        "mysql:host={$host};dbname={$db};charset=utf8mb4",
                        $user,
                        $pass,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 5
                        ]
                    );

                    echo "  ✅ ÉXITO: {$user}@{$host}/{$db} (password: " . ($pass ? 'sí' : 'no') . ")\n";

                    // Test adicional: verificar tablas
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo "     📋 Tablas encontradas: " . count($tables) . "\n";

                    if (in_array('transcriptions_temp', $tables)) {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM transcriptions_temp");
                        $count = $stmt->fetchColumn();
                        echo "     🔍 Reuniones temporales: {$count}\n";
                    }

                    $pdo = null;
                    echo "\n";
                    goto next_host; // Salir cuando encontremos una conexión exitosa

                } catch (PDOException $e) {
                    // Silenciar errores para probar todas las combinaciones
                }
            }
        }
    }
    echo "  ❌ No se pudo conectar con ninguna combinación\n\n";
    next_host:
}

echo "\n🔧 DIAGNÓSTICO DE SISTEMA:\n\n";

// Test 2: Verificar servicios
echo "MySQL Service Status:\n";
$mysqlStatus = shell_exec('systemctl status mysql 2>/dev/null') ?? shell_exec('service mysql status 2>/dev/null') ?? 'No disponible';
echo substr($mysqlStatus, 0, 200) . "...\n\n";

// Test 3: Verificar puertos
echo "Puertos MySQL en uso:\n";
$ports = shell_exec('netstat -tlnp | grep :3306 2>/dev/null') ?? 'No disponible';
echo $ports ?: "Puerto 3306 no encontrado\n";
echo "\n";

// Test 4: Verificar procesos MySQL
echo "Procesos MySQL activos:\n";
$processes = shell_exec('ps aux | grep mysql | grep -v grep 2>/dev/null') ?? 'No disponible';
echo $processes ?: "No hay procesos MySQL activos\n";
echo "\n";

// Test 5: Variables de entorno Laravel
echo "Variables de entorno (.env):\n";
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    $dbLines = array_filter(explode("\n", $envContent), function($line) {
        return strpos($line, 'DB_') === 0;
    });

    foreach ($dbLines as $line) {
        echo "  {$line}\n";
    }
} else {
    echo "  ❌ Archivo .env no encontrado\n";
}

echo "\n🛠️ COMANDOS PARA SOLUCIONAR PROBLEMAS:\n\n";

echo "1. Verificar estado del servicio MySQL:\n";
echo "   sudo systemctl status mysql\n";
echo "   sudo service mysql status\n\n";

echo "2. Iniciar MySQL si está detenido:\n";
echo "   sudo systemctl start mysql\n";
echo "   sudo service mysql start\n\n";

echo "3. Reiniciar MySQL:\n";
echo "   sudo systemctl restart mysql\n";
echo "   sudo service mysql restart\n\n";

echo "4. Verificar logs de error:\n";
echo "   sudo tail -f /var/log/mysql/error.log\n";
echo "   sudo journalctl -u mysql -f\n\n";

echo "5. Conectar manualmente a MySQL:\n";
echo "   mysql -u root -p\n";
echo "   mysql -u root\n\n";

echo "6. Verificar configuración MySQL:\n";
echo "   sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf\n";
echo "   (Buscar bind-address = 127.0.0.1)\n\n";

echo "7. Verificar espacio en disco:\n";
echo "   df -h\n";
echo "   du -sh /var/lib/mysql\n\n";

echo "8. Si MySQL no arranca, verificar permisos:\n";
echo "   sudo chown -R mysql:mysql /var/lib/mysql\n";
echo "   sudo chmod 755 /var/lib/mysql\n\n";

?>
