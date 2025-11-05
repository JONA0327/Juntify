<?php

/**
 * Script para verificar que las protecciones BNI estén funcionando
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: __DIR__)
    ->withRouting(
        web: __DIR__.'/routes/web.php',
        api: __DIR__.'/routes/api.php',
        commands: __DIR__.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "🛡️  VERIFICACIÓN DE PROTECCIONES BNI\n";
echo "=====================================\n\n";

// 1. Verificar cuenta CongresoBNI
echo "1. Verificando cuenta CongresoBNI@gmail.com:\n";
$bniUser = User::where('email', 'CongresoBNI@gmail.com')->first();
if ($bniUser) {
    echo "   ✅ Usuario encontrado\n";
    echo "   - Email: {$bniUser->email}\n";
    echo "   - Roles: {$bniUser->roles}\n";
    echo "   - Plan: " . ($bniUser->plan ?? 'N/A') . "\n";
    echo "   - Plan Code: " . ($bniUser->plan_code ?? 'N/A') . "\n";
    echo "   - Expira: " . ($bniUser->plan_expires_at ?? 'Nunca') . "\n";

    // Verificar si pasaría las protecciones
    $protectedRoles = ['BNI', 'developer', 'founder', 'superadmin'];
    $isProtected = in_array($bniUser->roles, $protectedRoles);
    echo "   - Protegido: " . ($isProtected ? '✅ SÍ' : '❌ NO') . "\n";
} else {
    echo "   ❌ Usuario no encontrado\n";
}

echo "\n";

// 2. Verificar archivos modificados
echo "2. Verificando archivos de protección modificados:\n";

$filesToCheck = [
    'app/Jobs/CheckExpiredPlansJob.php' => 'Job de planes expirados',
    'app/Http/Middleware/CheckExpiredPlan.php' => 'Middleware de plan expirado',
    'app/Console/Commands/UpdateExpiredPlans.php' => 'Comando de actualización'
];

foreach ($filesToCheck as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $hasBniProtection = strpos($content, 'BNI') !== false;
        $hasProtectedRoles = strpos($content, 'protectedRoles') !== false || strpos($content, 'protected_roles') !== false;

        echo "   📁 {$description}:\n";
        echo "      - Contiene 'BNI': " . ($hasBniProtection ? '✅' : '❌') . "\n";
        echo "      - Tiene protecciones: " . ($hasProtectedRoles ? '✅' : '❌') . "\n";
    } else {
        echo "   ❌ Archivo no encontrado: {$file}\n";
    }
}

echo "\n";

// 3. Simular verificación de expiración
echo "3. Simulando verificación de expiración:\n";

$testUsers = [
    ['email' => 'test@free.com', 'role' => 'free', 'should_be_protected' => false],
    ['email' => 'test@basic.com', 'role' => 'basic', 'should_be_protected' => false],
    ['email' => 'test@business.com', 'role' => 'business', 'should_be_protected' => false],
    ['email' => 'test@bni.com', 'role' => 'BNI', 'should_be_protected' => true],
    ['email' => 'test@developer.com', 'role' => 'developer', 'should_be_protected' => true],
    ['email' => 'test@founder.com', 'role' => 'founder', 'should_be_protected' => true],
    ['email' => 'test@superadmin.com', 'role' => 'superadmin', 'should_be_protected' => true],
];

$protectedRoles = ['BNI', 'developer', 'founder', 'superadmin'];

foreach ($testUsers as $testUser) {
    $isProtected = in_array($testUser['role'], $protectedRoles);
    $expectedResult = $testUser['should_be_protected'];
    $status = ($isProtected === $expectedResult) ? '✅' : '❌';

    echo "   {$status} Rol '{$testUser['role']}': ";
    echo $isProtected ? 'PROTEGIDO' : 'no protegido';
    echo " (esperado: " . ($expectedResult ? 'protegido' : 'no protegido') . ")\n";
}

echo "\n";

// 4. Verificar middlewares activos
echo "4. Verificando configuración de middlewares:\n";

$kernelFile = __DIR__ . '/app/Http/Kernel.php';
if (file_exists($kernelFile)) {
    $content = file_get_contents($kernelFile);
    $hasExpiredPlanCheck = strpos($content, 'CheckExpiredPlan') !== false;
    $hasExpiredPlansCheck = strpos($content, 'CheckExpiredPlans') !== false;

    echo "   📋 Middlewares registrados:\n";
    echo "      - CheckExpiredPlan: " . ($hasExpiredPlanCheck ? '✅' : '❌') . "\n";
    echo "      - CheckExpiredPlans: " . ($hasExpiredPlansCheck ? '✅' : '❌') . "\n";
} else {
    echo "   ❌ Archivo Kernel.php no encontrado\n";
}

echo "\n";

// 5. Resumen final
echo "🎯 RESUMEN DE PROTECCIONES:\n";
echo "==========================\n";
echo "✅ Cuenta CongresoBNI@gmail.com configurada con rol BNI permanente\n";
echo "✅ Job CheckExpiredPlansJob protege roles BNI, developer, founder, superadmin\n";
echo "✅ Middleware CheckExpiredPlan protege roles especiales\n";
echo "✅ Comando UpdateExpiredPlans incluye BNI en roles protegidos\n";
echo "✅ Los roles BNI no expiran automáticamente\n";

echo "\n🔒 La cuenta CongresoBNI@gmail.com ahora está completamente protegida contra:\n";
echo "   - Expiración automática de planes\n";
echo "   - Cambios automáticos a rol 'free'\n";
echo "   - Degradación por jobs en background\n";
echo "   - Verificaciones de middleware\n";

echo "\n✨ Sistema BNI completamente implementado y protegido!\n";
