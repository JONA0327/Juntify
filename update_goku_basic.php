<?php
/**
 * Script simple para actualizar Plan Basic del usuario goku
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "🔄 Actualizando usuario goku03278@gmail.com...\n\n";

// Fecha de expiración: 6 meses desde ahora
$newExpiry = date('Y-m-d H:i:s', strtotime('+6 months'));

$stmt = $pdo->prepare("
    UPDATE users
    SET
        roles = 'basic',
        plan_expires_at = ?,
        updated_at = NOW()
    WHERE email = 'goku03278@gmail.com'
");

$result = $stmt->execute([$newExpiry]);

if ($result) {
    echo "✅ Usuario actualizado exitosamente!\n";
    echo "   Plan: Basic\n";
    echo "   Vigente hasta: {$newExpiry}\n\n";

    // Verificar resultado
    $stmt = $pdo->prepare("
        SELECT full_name, email, roles, plan_expires_at
        FROM users
        WHERE email = 'goku03278@gmail.com'
    ");
    $stmt->execute();
    $user = $stmt->fetch();

    echo "📊 Estado final:\n";
    echo "   Nombre: {$user['full_name']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Plan: {$user['roles']}\n";
    echo "   Expira: {$user['plan_expires_at']}\n";

    $daysLeft = ceil((strtotime($user['plan_expires_at']) - time()) / (60 * 60 * 24));
    echo "   Días restantes: {$daysLeft}\n\n";

    echo "🎉 LISTO! Ahora el usuario puede crear contenedores.\n";
} else {
    echo "❌ Error actualizando usuario.\n";
}
?>
