<?php
/**
 * Script para cambiar fecha del Plan Basic a 1 día para pruebas
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "🧪 Actualizando fecha para pruebas...\n\n";

// Fecha de expiración: mañana (1 día desde ahora)
$newExpiry = date('Y-m-d H:i:s', strtotime('+1 day'));

$stmt = $pdo->prepare("
    UPDATE users
    SET
        plan_expires_at = ?,
        updated_at = NOW()
    WHERE email = 'goku03278@gmail.com'
");

$result = $stmt->execute([$newExpiry]);

if ($result) {
    echo "✅ Fecha actualizada para pruebas!\n";
    echo "   Plan expira: {$newExpiry}\n\n";

    // Verificar resultado
    $stmt = $pdo->prepare("
        SELECT full_name, email, roles, plan_expires_at
        FROM users
        WHERE email = 'goku03278@gmail.com'
    ");
    $stmt->execute();
    $user = $stmt->fetch();

    echo "📊 Estado para pruebas:\n";
    echo "   Nombre: {$user['full_name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Plan: {$user['roles']}\n";
    echo "   Expira: {$user['plan_expires_at']}\n";

    $timeLeft = strtotime($user['plan_expires_at']) - time();
    $hoursLeft = ceil($timeLeft / (60 * 60));
    echo "   Horas restantes: {$hoursLeft}\n\n";

    echo "🧪 LISTO! Plan expira mañana - Perfecto para pruebas.\n";
} else {
    echo "❌ Error actualizando fecha.\n";
}
?>
