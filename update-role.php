<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::table('users')
    ->where('email', 'jona03278@gmail.com')
    ->update([
        'roles' => 'developer',
        'plan_code' => 'developer',
        'plan' => 'developer'
    ]);

echo "✓ Rol, plan_code y plan actualizados a developer\n";
