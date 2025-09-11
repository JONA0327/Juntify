<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Database for Testing ===\n";

// Check users
$users = DB::table('users')->select('id', 'email', 'full_name')->orderBy('created_at', 'desc')->limit(3)->get();
echo "\nRecent Users:\n";
foreach ($users as $user) {
    echo "ID: {$user->id}, Email: {$user->email}, Name: {$user->full_name}\n";
}

// Check legacy meetings with Drive IDs
$legacy = DB::table('transcriptions_laravel')
    ->whereNotNull('transcript_drive_id')
    ->orWhereNotNull('audio_drive_id')
    ->select('id', 'meeting_name', 'transcript_drive_id', 'audio_drive_id')
    ->limit(3)
    ->get();

echo "\nLegacy Meetings with Drive IDs:\n";
foreach ($legacy as $meeting) {
    echo "ID: {$meeting->id}, Name: {$meeting->meeting_name}, Transcript: {$meeting->transcript_drive_id}, Audio: {$meeting->audio_drive_id}\n";
}

// Check modern meetings
$modern = DB::table('meetings')->select('id', 'title', 'recordings_folder_id')->whereNotNull('recordings_folder_id')->limit(3)->get();
echo "\nModern Meetings with Folder IDs:\n";
foreach ($modern as $meeting) {
    echo "ID: {$meeting->id}, Title: {$meeting->title}, Folder: {$meeting->recordings_folder_id}\n";
}

// Check existing shared meetings
$shared = DB::table('shared_meetings')->count();
echo "\nExisting Shared Meetings: {$shared}\n";
