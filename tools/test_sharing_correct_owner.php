<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SharedMeetingController;
use App\Models\User;
use App\Models\Contact;
use App\Models\SharedMeeting;
use Illuminate\Http\Request;

echo "=== Testing Meeting Sharing with Actual Owner ===\n";

// Get the meeting owner
$owner = User::where('email', 'jona03278@gmail.com')->first();
if (!$owner) {
    echo "Owner not found\n";
    exit;
}

// Get a recipient (different user)
$recipient = User::where('email', 'fanlee1996@gmail.com')->first();
if (!$recipient) {
    echo "Recipient not found\n";
    exit;
}

echo "Owner: {$owner->email} (username: {$owner->username})\n";
echo "Recipient: {$recipient->email} (username: {$recipient->username})\n";

// Check if owner has a valid token
if (!$owner->googleToken) {
    echo "Owner doesn't have Google token\n";
    exit;
}

// Clean up previous test sharing
SharedMeeting::where('meeting_id', 49)
    ->where('shared_by', $owner->id)
    ->where('shared_with', $recipient->id)
    ->delete();

// Make sure there's a contact relationship
$contact = Contact::where('user_id', $owner->id)
    ->where('contact_id', $recipient->id)
    ->first();

if (!$contact) {
    echo "Creating contact relationship...\n";
    Contact::create([
        'id' => \Illuminate\Support\Str::uuid(),
        'user_id' => $owner->id,
        'contact_id' => $recipient->id
    ]);
}

// Authenticate as the OWNER (not a random user)
Auth::login($owner);

// Use legacy meeting that belongs to the owner
$meetingId = 49; // "Entrevista Miedo" owned by Jona0327

echo "Testing sharing of meeting ID: {$meetingId} by actual owner\n";

// Test sharing with the correct ownership
try {
    echo "Calling shareMeeting as file owner...\n";

    $controller = new SharedMeetingController();

    // Create request
    $request = new Request([
        'meeting_id' => $meetingId,
        'contact_ids' => [$recipient->id],
        'message' => 'Test sharing by file owner'
    ]);

    $response = $controller->shareMeeting($request);
    echo "Response: " . $response->getContent() . "\n";

    // Get the shared meeting record
    $sharedMeeting = SharedMeeting::where('meeting_id', $meetingId)
        ->where('shared_by', $owner->id)
        ->where('shared_with', $recipient->id)
        ->latest()
        ->first();

    if ($sharedMeeting) {
        echo "Shared meeting record created with ID: {$sharedMeeting->id}\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Check logs for Drive permission details ===\n";
