<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionLaravel;
use App\Models\User;

echo "=== All meetings with Drive files ===\n";
$meetings = TranscriptionLaravel::where(function($query) {
    $query->whereNotNull('transcript_drive_id')
          ->orWhereNotNull('audio_drive_id');
})->get(['id', 'meeting_name', 'username']);

foreach($meetings as $m) {
    echo "ID: {$m->id}, Name: {$m->meeting_name}, User: {$m->username}\n";
}

echo "\n=== Available users ===\n";
$users = User::limit(10)->get(['username', 'email']);
foreach($users as $u) {
    echo "Username: {$u->username}, Email: {$u->email}\n";
}

echo "\n=== Finding matches ===\n";
foreach($meetings as $meeting) {
    $user = User::where('username', $meeting->username)->first();
    if ($user) {
        echo "Meeting '{$meeting->meeting_name}' belongs to {$user->email} (has token: " . ($user->googleToken ? 'YES' : 'NO') . ")\n";
    } else {
        echo "Meeting '{$meeting->meeting_name}' has no matching user for username: {$meeting->username}\n";
    }
}
