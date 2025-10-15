<?php

require_once __DIR__ . '/vendor/autoload.php';

// Simular la lógica del navbar actualizada
function testTasksAccess($userPlan, $hasGroups, $userRoles) {
    echo "\n=== Test: Plan=$userPlan, HasGroups=$hasGroups, Roles=$userRoles ===\n";

    // Lógica del navbar actualizada (usando 'roles' no 'role')
    $belongsToOrg = $hasGroups ||
                   in_array($userRoles, ['admin', 'superadmin', 'founder', 'developer']);

    $hasTasksAccess = $userPlan !== 'free' || $belongsToOrg;

    echo "belongsToOrg: " . ($belongsToOrg ? 'true' : 'false') . "\n";
    echo "hasTasksAccess: " . ($hasTasksAccess ? 'true' : 'false') . "\n";
    echo "Should show modal: " . ($hasTasksAccess ? 'NO' : 'YES') . "\n";

    return $hasTasksAccess;
}

echo "🧪 Probando lógica de acceso a tareas (CORREGIDA)\n";

// Caso 1: Usuario FREE sin organización
testTasksAccess('free', false, null);

// Caso 2: Usuario FREE con organización (grupos)
testTasksAccess('free', true, null);

// Caso 3: Usuario FREE con rol organizacional (admin)
testTasksAccess('free', false, 'admin');

// Caso 4: Usuario con rol DEVELOPER (acceso completo)
testTasksAccess('free', false, 'developer');

// Caso 5: Usuario con rol SUPERADMIN (acceso completo)
testTasksAccess('free', false, 'superadmin');

// Caso 6: Usuario con rol FOUNDER (acceso completo)
testTasksAccess('free', false, 'founder');

// Caso 7: Usuario Premium
testTasksAccess('business', false, null);

// Caso 8: Usuario Enterprise
testTasksAccess('enterprise', false, null);

// Caso 9: Usuario FREE con grupos Y rol organizacional
testTasksAccess('free', true, 'developer');

echo "\n✅ Pruebas completadas - Los roles developer, founder, superadmin deben tener acceso completo\n";
