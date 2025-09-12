<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$meeting = DB::table('transcriptions_laravel')->where('id', 56)->first(['id', 'meeting_name', 'username', 'transcript_drive_id', 'audio_drive_id']);
if ($meeting) {
    echo "ID: {$meeting->id}\n";
    echo "Nombre: {$meeting->meeting_name}\n";
    echo "Usuario: {$meeting->username}\n";
    echo "Transcript Drive ID: " . ($meeting->transcript_drive_id ?: 'NULL') . "\n";
    echo "Audio Drive ID: " . ($meeting->audio_drive_id ?: 'NULL') . "\n";
}
