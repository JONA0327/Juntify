<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\TranscriptionLaravel;

echo "=== Finding Meetings Owned by Test Users ===\n";

$users = User::orderBy('created_at', 'desc')->limit(3)->get();

foreach ($users as $user) {
    echo "\nUser: {$user->email} (username: {$user->username})\n";

    // Check legacy meetings
    $legacyMeetings = TranscriptionLaravel::where('username', $user->username)
        ->where(function($query) {
            $query->whereNotNull('transcript_drive_id')
                  ->orWhereNotNull('audio_drive_id');
        })
        ->limit(3)
        ->get(['id', 'meeting_name', 'transcript_drive_id', 'audio_drive_id']);

    echo "Legacy meetings: " . $legacyMeetings->count() . "\n";
    foreach ($legacyMeetings as $meeting) {
        echo "  - ID: {$meeting->id}, Name: {$meeting->meeting_name}\n";
        echo "    Transcript: {$meeting->transcript_drive_id}\n";
        echo "    Audio: {$meeting->audio_drive_id}\n";
    }

    echo "Has Google token: " . ($user->googleToken ? 'YES' : 'NO') . "\n";
}
