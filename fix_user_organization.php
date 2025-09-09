<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::where('username', 'Jona0327')->first();

echo "Antes:\n";
echo "current_organization_id: " . ($user->current_organization_id ?? 'null') . "\n";

$user->current_organization_id = 12;
$user->save();

echo "\nDespués:\n";
echo "current_organization_id: " . $user->current_organization_id . "\n";
echo "✅ Usuario actualizado correctamente\n";
