<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$meeting = Illuminate\Support\Facades\DB::table('transcriptions_laravel')->first();
if ($meeting) {
    echo "Username en reunión: {$meeting->username}\n";
    echo "Meeting name: {$meeting->meeting_name}\n";
    
    $user = Illuminate\Support\Facades\DB::table('users')->where('username', $meeting->username)->first(['id', 'username', 'email']);
    if ($user) {
        echo "Usuario ID: {$user->id}\n";
        echo "Email: {$user->email}\n";
    } else {
        echo "Usuario no encontrado con username: {$meeting->username}\n";
    }
} else {
    echo "No hay reuniones en la tabla\n";
}
