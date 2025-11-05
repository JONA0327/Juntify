<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::where('email', 'CongresoBNI@gmail.com')->first();
if (! $user) {
    echo "Usuario no encontrado\n";
    exit(1);
}

$user->is_role_protected = true;
$user->roles = 'BNI';
$user->plan = 'BNI';
$user->plan_code = 'BNI';
$user->plan_expires_at = null;
$user->save();

echo "Protegido y actualizado: {$user->email} -> roles={$user->roles}, is_role_protected=" . ($user->is_role_protected ? '1' : '0') . "\n";
