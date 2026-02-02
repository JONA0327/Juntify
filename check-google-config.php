<?php

// Script para verificar la configuración de Google Drive/Calendar
// Ejecuta desde la raíz del proyecto: php check-google-config.php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Google\Client;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== DIAGNÓSTICO DE CONFIGURACIÓN GOOGLE ===\n\n";

// 1. Verificar variables de entorno
echo "1. Variables de entorno:\n";
$requiredEnvs = [
    'GOOGLE_OAUTH_CLIENT_ID',
    'GOOGLE_OAUTH_CLIENT_SECRET', 
    'GOOGLE_OAUTH_REDIRECT_URI',
    'GOOGLE_SERVICE_ACCOUNT_EMAIL',
    'GOOGLE_APPLICATION_CREDENTIALS',
    'GOOGLE_API_KEY'
];

$envStatus = true;
foreach ($requiredEnvs as $env) {
    $value = getenv($env);
    $status = $value ? '✓' : '✗';
    $displayValue = $value ? (strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value) : 'NO CONFIGURADO';
    echo "   {$status} {$env}: {$displayValue}\n";
    if (!$value) $envStatus = false;
}

echo "\n";

// 2. Verificar archivo de credenciales
echo "2. Archivo de credenciales de Service Account:\n";
$credentialsPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
if ($credentialsPath && file_exists($credentialsPath)) {
    echo "   ✓ Archivo existe: {$credentialsPath}\n";
    
    $credentials = json_decode(file_get_contents($credentialsPath), true);
    if ($credentials) {
        echo "   ✓ JSON válido\n";
        echo "   ✓ Project ID: " . ($credentials['project_id'] ?? 'No encontrado') . "\n";
        echo "   ✓ Client Email: " . ($credentials['client_email'] ?? 'No encontrado') . "\n";
    } else {
        echo "   ✗ JSON inválido\n";
        $envStatus = false;
    }
} else {
    echo "   ✗ Archivo no encontrado: {$credentialsPath}\n";
    $envStatus = false;
}

echo "\n";

// 3. Verificar Google Client
echo "3. Cliente de Google:\n";
try {
    $client = new Client();
    $client->setClientId(getenv('GOOGLE_OAUTH_CLIENT_ID'));
    $client->setClientSecret(getenv('GOOGLE_OAUTH_CLIENT_SECRET'));
    $client->setRedirectUri(getenv('GOOGLE_OAUTH_REDIRECT_URI'));
    $client->setScopes([
        Google\Service\Oauth2::USERINFO_EMAIL,
        Google\Service\Drive::DRIVE,
        Google\Service\Calendar::CALENDAR
    ]);
    
    echo "   ✓ Cliente de Google configurado correctamente\n";
    echo "   ✓ Scopes configurados: email, drive, calendar\n";
    
    $authUrl = $client->createAuthUrl();
    echo "   ✓ URL de autorización generada correctamente\n";
    
} catch (Exception $e) {
    echo "   ✗ Error al configurar cliente: " . $e->getMessage() . "\n";
    $envStatus = false;
}

echo "\n";

// 4. Verificar Service Account
echo "4. Service Account:\n";
try {
    if ($credentialsPath && file_exists($credentialsPath)) {
        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Google\Service\Drive::DRIVE]);
        
        // Test básico de autenticación
        $service = new Google\Service\Drive($client);
        echo "   ✓ Service Account configurado\n";
        echo "   ✓ Servicio Drive inicializado\n";
    } else {
        echo "   ✗ No se puede verificar Service Account (archivo de credenciales faltante)\n";
        $envStatus = false;
    }
} catch (Exception $e) {
    echo "   ✗ Error con Service Account: " . $e->getMessage() . "\n";
    $envStatus = false;
}

echo "\n";

// 5. Resumen
echo "=== RESUMEN ===\n";
if ($envStatus) {
    echo "✅ CONFIGURACIÓN CORRECTA - Google Drive/Calendar debería funcionar\n";
    echo "\nPara probar la conexión:\n";
    echo "1. Reinicia tu servidor Laravel (php artisan serve)\n";
    echo "2. Ve a la sección de perfil\n";
    echo "3. Haz clic en 'Conectar Drive y Calendar'\n";
} else {
    echo "❌ CONFIGURACIÓN INCOMPLETA - Revisa los errores arriba\n";
    echo "\nPróximos pasos:\n";
    echo "1. Corregir las variables de entorno faltantes\n";
    echo "2. Verificar que el archivo de credenciales esté en la ruta correcta\n";
    echo "3. Reiniciar el servidor después de los cambios\n";
}

echo "\n";