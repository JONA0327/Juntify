<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Buscando reunión ID 56...\n";

$legacyMeeting = DB::table('transcriptions_laravel')->where('id', 56)->first();
if ($legacyMeeting) {
    echo "Encontrada en transcriptions_laravel:\n";
    echo "ID: {$legacyMeeting->id}\n";
    echo "Nombre: {$legacyMeeting->meeting_name}\n";
    echo "Username: {$legacyMeeting->username}\n";
} else {
    echo "No encontrada en transcriptions_laravel\n";
}

$modernMeeting = DB::table('meetings')->where('id', 56)->first();
if ($modernMeeting) {
    echo "Encontrada en meetings:\n";
    echo "ID: {$modernMeeting->id}\n";
    echo "Título: {$modernMeeting->title}\n";
    echo "Username: {$modernMeeting->username}\n";
} else {
    echo "No encontrada en meetings\n";
}
