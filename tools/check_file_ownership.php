<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Services\GoogleDriveService;
use App\Services\GoogleTokenRefreshService;

echo "=== Checking File Ownership and Access ===\n";

// Check the legacy meeting details
$meeting = TranscriptionLaravel::find(49);
if ($meeting) {
    echo "Meeting: {$meeting->meeting_name}\n";
    echo "User ID: {$meeting->user_id}\n";
    echo "Username: {$meeting->username}\n";
    echo "Transcript file: {$meeting->transcript_drive_id}\n";
    echo "Audio file: {$meeting->audio_drive_id}\n";

    // Find the actual user who owns this meeting
    $owner = User::where('username', $meeting->username)->first();
    if ($owner) {
        echo "Owner found: {$owner->email} (ID: {$owner->id})\n";
        echo "Owner has token: " . ($owner->googleToken ? 'YES' : 'NO') . "\n";

        if ($owner->googleToken) {
            // Try to test access with the actual owner's token
            try {
                $refreshService = app(GoogleTokenRefreshService::class);
                $refreshResult = $refreshService->refreshTokenForUser($owner);
                echo "Owner token refresh: " . ($refreshResult ? 'SUCCESS' : 'FAILED') . "\n";

                $owner->refresh();
                $drive = app(GoogleDriveService::class);
                $token = $owner->googleToken;
                $drive->setAccessToken($token->access_token ? json_decode($token->access_token, true) ?: ['access_token' => $token->access_token] : []);

                // Test file access
                $driveService = $drive->getDrive();
                $file = $driveService->files->get($meeting->transcript_drive_id, ['fields' => 'id,name,owners']);
                echo "File access by owner: SUCCESS\n";
                echo "File name: " . $file->getName() . "\n";
                $owners = $file->getOwners();
                if ($owners) {
                    echo "Actual file owner: " . $owners[0]->getEmailAddress() . "\n";
                }

            } catch (\Throwable $e) {
                echo "Owner file access failed: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "No user found with username: {$meeting->username}\n";
    }
} else {
    echo "Meeting not found\n";
}

echo "\nComparing with sharer:\n";
$sharer = User::where('email', 'fanlee1996@gmail.com')->first();
echo "Sharer: {$sharer->email}\n";
echo "Sharer username: {$sharer->username}\n";
echo "Meeting username: {$meeting->username}\n";
echo "Same user: " . ($sharer->username === $meeting->username ? 'YES' : 'NO') . "\n";
