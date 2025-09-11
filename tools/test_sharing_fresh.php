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

echo "=== Testing Meeting Sharing (User Token Only) ===\n";

// Get users for testing
$users = User::orderBy('created_at', 'desc')->limit(2)->get();
if ($users->count() < 2) {
    echo "Not enough users for testing\n";
    exit;
}

$sharer = $users[0];
$recipient = $users[1];

echo "Sharer: {$sharer->email} (ID: {$sharer->id})\n";
echo "Recipient: {$recipient->email} (ID: {$recipient->id})\n";

// Check if sharer has a valid token first
if (!$sharer->googleToken) {
    echo "Sharer doesn't have Google token\n";
    exit;
}

// Clean up previous test sharing
SharedMeeting::where('meeting_id', 49)
    ->where('shared_by', $sharer->id)
    ->where('shared_with', $recipient->id)
    ->delete();

// Make sure there's a contact relationship
$contact = Contact::where('user_id', $sharer->id)
    ->where('contact_id', $recipient->id)
    ->first();

if (!$contact) {
    echo "Creating contact relationship...\n";
    Contact::create([
        'id' => \Illuminate\Support\Str::uuid(),
        'user_id' => $sharer->id,
        'contact_id' => $recipient->id
    ]);
}

// Authenticate as sharer
Auth::login($sharer);

// Use legacy meeting with Drive IDs for testing
$meetingId = 49; // "Entrevista Miedo" from our database check

echo "Testing sharing of legacy meeting ID: {$meetingId}\n";

// Test sharing with improved logic
try {
    echo "Calling shareMeeting with improved authentication...\n";

    $controller = new SharedMeetingController();

    // Create request
    $request = new Request([
        'meeting_id' => $meetingId,
        'contact_ids' => [$recipient->id],
        'message' => 'Test sharing with token refresh'
    ]);

    $response = $controller->shareMeeting($request);
    echo "Response: " . $response->getContent() . "\n";

    // Get the shared meeting record
    $sharedMeeting = SharedMeeting::where('meeting_id', $meetingId)
        ->where('shared_by', $sharer->id)
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
