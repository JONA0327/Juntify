<?php

use Illuminate\Support\Facades\DB;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userId = 'a2c8514d-932c-4bc9-8a2b-e7355faa25ad';
$username = 'Jonalp0327';

echo "=== DEBUGGING SHARED MEETINGS ACCESS ===\n";

// 1. Check user exists
$user = User::where('username', $username)->first();
if (!$user) {
    echo "ERROR: User not found\n";
    exit;
}
echo "✅ User found: {$user->username} (ID: {$user->id})\n\n";

// 2. Check shared meetings
$sharedMeetings = DB::table('shared_meetings')
    ->where('shared_with', $user->id)
    ->where('status', 'accepted')
    ->where('meeting_type', 'transcriptions_laravel')
    ->get();

echo "📋 Shared meetings for user:\n";
foreach($sharedMeetings as $sm) {
    echo "   Meeting ID: {$sm->meeting_id} (Owner: {$sm->owner_id}, Status: {$sm->status})\n";
}
echo "Total shared meetings: " . $sharedMeetings->count() . "\n\n";

// 3. Check if meeting 128 is specifically shared
$meeting128 = DB::table('shared_meetings')
    ->where('shared_with', $user->id)
    ->where('meeting_id', 128)
    ->where('status', 'accepted')
    ->where('meeting_type', 'transcriptions_laravel')
    ->first();

if ($meeting128) {
    echo "✅ Meeting 128 IS shared with user\n";
} else {
    echo "❌ Meeting 128 is NOT shared with user\n";

    // Check if it exists but with different status
    $meeting128Any = DB::table('shared_meetings')
        ->where('shared_with', $user->id)
        ->where('meeting_id', 128)
        ->first();

    if ($meeting128Any) {
        echo "   But found with status: {$meeting128Any->status}, type: {$meeting128Any->meeting_type}\n";
    } else {
        echo "   No sharing record found at all for meeting 128\n";
    }
}

// 4. Test the actual query used in scopeVisibleTasks
$subQuery = DB::table('shared_meetings')
    ->select('meeting_id')
    ->where('shared_with', $user->id)
    ->where('status', 'accepted')
    ->where('meeting_type', 'transcriptions_laravel');

echo "\n🔍 Shared meeting IDs query returns:\n";
$meetingIds = $subQuery->pluck('meeting_id')->toArray();
echo "   IDs: [" . implode(', ', $meetingIds) . "]\n";

echo "\n=== END DEBUG ===\n";
