<?php
/**
 * Script para asignar Plan Basic vigente al usuario goku03278@gmail.com
 *
 * Fecha: 15 Octubre 2025
 * Propósito: Solucionar problema de plan vencido que impedía crear contenedores
 */

require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configurar conexión a base de datos
$config = [
    'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'forge',
    'username' => $_ENV['DB_USERNAME'] ?? 'forge',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "🔗 Conectado a la base de datos: {$config['database']}\n\n";

    // =====================================
    // BUSCAR EL USUARIO
    // =====================================
    $userEmail = 'goku03278@gmail.com';

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, roles, plan_expires_at
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$userEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "❌ Usuario {$userEmail} no encontrado.\n";
        exit(1);
    }

    echo "👤 Usuario encontrado:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Nombre: {$user['full_name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Roles: {$user['roles']}\n";
    echo "   Plan expira: {$user['plan_expires_at']}\n\n";

    // =====================================
    // BUSCAR PLAN BASIC
    // =====================================
    $stmt = $pdo->prepare("
        SELECT * FROM plans
        WHERE code = 'basico'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $basicPlan = $stmt->fetch();

    if (!$basicPlan) {
        echo "❌ Plan Basic no encontrado en la base de datos.\n";
        exit(1);
    }

    echo "📋 Plan Basic encontrado:\n";
    echo "   ID: {$basicPlan['id']}\n";
    echo "   Código: {$basicPlan['code']}\n";
    echo "   Nombre: {$basicPlan['name']}\n";
    echo "   Precio: {$basicPlan['price']}\n";
    echo "   Features: " . substr($basicPlan['features'], 0, 100) . "...\n\n";

    // =====================================
    // VERIFICAR SUSCRIPCIÓN ACTUAL
    // =====================================
    $stmt = $pdo->prepare("
        SELECT * FROM user_subscriptions
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $currentSubscription = $stmt->fetch();

    if ($currentSubscription) {
        echo "📊 Suscripción actual:\n";
        echo "   Plan ID: {$currentSubscription['plan_id']}\n";
        echo "   Inicio: {$currentSubscription['start_date']}\n";
        echo "   Fin: {$currentSubscription['end_date']}\n";
        echo "   Estado: {$currentSubscription['status']}\n\n";
    } else {
        echo "ℹ️ No hay suscripción actual.\n\n";
    }

    // =====================================
    // ASIGNAR PLAN BASIC VIGENTE
    // =====================================

    // Fechas: desde hoy hasta en 6 meses
    $startDate = date('Y-m-d H:i:s');
    $endDate = date('Y-m-d H:i:s', strtotime('+6 months'));

    echo "🔄 Creando nueva suscripción Plan Basic...\n";
    echo "   Fecha inicio: {$startDate}\n";
    echo "   Fecha fin: {$endDate}\n";

    // Insertar nueva suscripción
    $stmt = $pdo->prepare("
        INSERT INTO user_subscriptions
        (user_id, plan_id, start_date, end_date, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    $stmt->execute([
        $user['id'],
        $basicPlan['id'],
        $startDate,
        $endDate
    ]);

    $newSubscriptionId = $pdo->lastInsertId();

    echo "✅ Nueva suscripción creada con ID: {$newSubscriptionId}\n\n";

    // =====================================
    // ACTUALIZAR USUARIO
    // =====================================
    echo "🔄 Actualizando usuario a Plan Basic...\n";

    // Nuevo plan expira en 6 meses
    $newPlanExpiry = date('Y-m-d H:i:s', strtotime('+6 months'));

    $stmt = $pdo->prepare("
        UPDATE users
        SET roles = 'basic', plan_expires_at = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newPlanExpiry, $user['id']]);

    echo "✅ Usuario actualizado con Plan Basic vigente hasta: {$newPlanExpiry}\n\n";

    // =====================================
    // VERIFICAR RESULTADO FINAL
    // =====================================
    echo "🔍 Verificación final:\n";

    // Usuario actualizado
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, roles, plan_expires_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $updatedUser = $stmt->fetch();

    echo "👤 Usuario actualizado:\n";
    echo "   Roles: {$updatedUser['roles']}\n";
    echo "   Plan expira: {$updatedUser['plan_expires_at']}\n";

    // Suscripción activa
    $stmt = $pdo->prepare("
        SELECT us.*, p.code as plan_code, p.name as plan_name
        FROM user_subscriptions us
        JOIN plans p ON us.plan_id = p.id
        WHERE us.user_id = ?
        AND us.status = 'active'
        AND us.start_date <= NOW()
        AND us.end_date > NOW()
        ORDER BY us.id DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $activeSubscription = $stmt->fetch();

    if ($activeSubscription) {
        echo "📋 Suscripción activa:\n";
        echo "   Plan: {$activeSubscription['plan_code']} ({$activeSubscription['plan_name']})\n";
        echo "   Vigente hasta: {$activeSubscription['end_date']}\n";
        echo "   Estado: {$activeSubscription['status']}\n";

        $daysLeft = ceil((strtotime($activeSubscription['end_date']) - time()) / (60 * 60 * 24));
        echo "   Días restantes: {$daysLeft}\n";
    } else {
        echo "❌ No se encontró suscripción activa.\n";
    }

    echo "\n🎉 PROCESO COMPLETADO EXITOSAMENTE\n";
    echo "El usuario {$userEmail} ahora tiene Plan Basic vigente por 6 meses.\n";
    echo "Ya debería poder crear contenedores sin problemas.\n";

} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
    exit(1);
}
