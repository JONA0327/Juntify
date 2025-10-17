<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SharedMeeting;
use App\Models\TranscriptionLaravel;
use Illuminate\Support\Facades\Auth;

// Test regular shared meeting with Drive files
echo "=== Test: Shared Meeting with Drive Files ===\n\n";

// First, let's check if we have any shared regular meetings
echo "1. Checking for shared regular meetings:\n";
$sharedMeetings = SharedMeeting::with(['meeting', 'sharedBy'])
    ->where('meeting_type', 'regular')
    ->where('status', 'accepted')
    ->get();

foreach ($sharedMeetings as $shared) {
    echo "   - Shared Meeting ID: {$shared->id}\n";
    echo "     Meeting ID: {$shared->meeting_id}\n";
    $sharerEmail = $shared->sharedBy ? $shared->sharedBy->email : 'Unknown';
    echo "     Shared by: {$sharerEmail}\n";
    echo "     Shared with: {$shared->shared_with}\n";

    $meeting = $shared->meeting;
    if ($meeting) {
        echo "     Meeting Name: {$meeting->meeting_name}\n";
        echo "     Has transcript_drive_id: " . (!empty($meeting->transcript_drive_id) ? "Yes ({$meeting->transcript_drive_id})" : "No") . "\n";
        echo "     Has audio_drive_id: " . (!empty($meeting->audio_drive_id) ? "Yes ({$meeting->audio_drive_id})" : "No") . "\n";
        echo "     Summary in DB: " . (empty($meeting->summary) ? "Empty" : "Has content") . "\n";
        echo "     Transcription in DB: " . (empty($meeting->transcription) ? "Empty" : "Has content") . "\n";
    }
    echo "\n";
}

if ($sharedMeetings->isEmpty()) {
    echo "   No shared regular meetings found.\n";

    // Let's check what meetings we have with Drive files
    echo "\n2. Checking for regular meetings with Drive files:\n";
    $driveMeetings = TranscriptionLaravel::whereNotNull('transcript_drive_id')
        ->orWhereNotNull('audio_drive_id')
        ->take(5)
        ->get();

    foreach ($driveMeetings as $meeting) {
        echo "   - Meeting ID: {$meeting->id}\n";
        echo "     Name: {$meeting->meeting_name}\n";
        echo "     User ID: {$meeting->user_id}\n";
        echo "     transcript_drive_id: {$meeting->transcript_drive_id}\n";
        echo "     audio_drive_id: {$meeting->audio_drive_id}\n";
        echo "     Summary in DB: " . (empty($meeting->summary) ? "Empty" : "Has content") . "\n";
        echo "\n";
    }
}

echo "\n3. Testing API endpoint for shared meeting:\n";
if (!$sharedMeetings->isEmpty()) {
    $testShared = $sharedMeetings->first();
    echo "   Testing shared meeting ID: {$testShared->id}\n";

    // Check if there are tasks in the database for this meeting
    $meeting = $testShared->meeting;
    if ($meeting) {
        $tasksInDb = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
            ->where('username', $meeting->username)
            ->count();
        echo "   Tasks in DB for meeting {$meeting->id}: $tasksInDb\n";
    }

    // Simulate being logged in as the recipient
    Auth::loginUsingId($testShared->shared_with);

    try {
        $controller = new \App\Http\Controllers\SharedMeetingController();
        $response = $controller->showSharedMeeting($testShared->id);
        $data = $response->getData(true);

        if ($data['success']) {
            $meeting = $data['meeting'];
            echo "   ✓ API call successful\n";
            echo "   Summary: " . (empty($meeting['summary']) ? "Empty" : "Has content (" . strlen($meeting['summary']) . " chars)") . "\n";
            echo "   Key Points: " . (empty($meeting['key_points']) ? "Empty" : count($meeting['key_points']) . " points") . "\n";
            echo "   Transcription: " . (empty($meeting['transcription']) ? "Empty" : "Has content (" . strlen($meeting['transcription']) . " chars)") . "\n";
            echo "   Segments: " . (empty($meeting['segments']) ? "Empty" : count($meeting['segments']) . " segments") . "\n";
            echo "   Tasks: " . (empty($meeting['tasks']) ? "Empty" : count($meeting['tasks']) . " tasks") . "\n";
        } else {
            echo "   ✗ API call failed: {$data['message']}\n";
        }

    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "   No shared meetings to test.\n";
}echo "\nDone.\n";
