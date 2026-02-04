<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "USUARIOS CON ROLES FOUNDER/ENTERPRISE\n";
echo "========================================\n\n";

$users = App\Models\User::whereIn('roles', ['founder', 'enterprise'])->get(['id', 'username', 'email', 'roles']);

if ($users->count() > 0) {
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
        echo "Username: {$user->username}\n";
        echo "Email: {$user->email}\n";
        echo "Rol: {$user->roles}\n";
        echo "---\n";
    }
    echo "\nTotal: {$users->count()} usuarios\n";
} else {
    echo "⚠️ NO SE ENCONTRARON USUARIOS CON ROL FOUNDER O ENTERPRISE\n";
    echo "\nUsuarios disponibles:\n";
    $allUsers = App\Models\User::get(['id', 'username', 'email', 'roles']);
    foreach ($allUsers as $user) {
        echo "- {$user->username} ({$user->email}) - ROL: {$user->roles}\n";
    }
}

echo "\n========================================\n";
echo "EMPRESAS EXISTENTES\n";
echo "========================================\n\n";

$empresas = Illuminate\Support\Facades\DB::connection('juntify_panels')
    ->table('empresa')
    ->get();

if ($empresas->count() > 0) {
    foreach ($empresas as $empresa) {
        echo "ID: {$empresa->id}\n";
        echo "Nombre: {$empresa->nombre_empresa}\n";
        echo "ID Usuario: {$empresa->iduser}\n";
        echo "Rol: {$empresa->rol}\n";
        echo "---\n";
    }
    echo "\nTotal: {$empresas->count()} empresas\n";
} else {
    echo "No hay empresas registradas\n";
}
