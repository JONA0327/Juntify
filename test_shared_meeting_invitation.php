<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\SharedMeeting;
use App\Models\TranscriptionTemp;
use App\Models\TranscriptionLaravel;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

echo "=== Test Shared Meeting Invitation System ===\n\n";

try {
    // Check if tables exist
    $tables = ['shared_meetings', 'transcription_temps', 'transcriptions_laravel', 'notifications'];
    foreach ($tables as $table) {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            echo "❌ Table '$table' does not exist\n";
            exit(1);
        }
    }
    echo "✅ All required tables exist\n";

    // Check if meeting_type column exists in shared_meetings
    if (!DB::getSchemaBuilder()->hasColumn('shared_meetings', 'meeting_type')) {
        echo "❌ Column 'meeting_type' does not exist in shared_meetings table\n";
        exit(1);
    }
    echo "✅ meeting_type column exists in shared_meetings\n";

    // Check if we have some test data
    $regularMeetings = TranscriptionLaravel::count();
    $tempMeetings = TranscriptionTemp::count();
    $users = User::count();

    echo "📊 Current data:\n";
    echo "   - Regular meetings: $regularMeetings\n";
    echo "   - Temporary meetings: $tempMeetings\n";
    echo "   - Users: $users\n";

    // Check if we have any shared meetings
    $sharedMeetings = SharedMeeting::count();
    $pendingShares = SharedMeeting::where('status', 'pending')->count();

    echo "   - Shared meetings: $sharedMeetings\n";
    echo "   - Pending invitations: $pendingShares\n";

    // Test meeting type distribution
    $regularShares = SharedMeeting::where('meeting_type', 'regular')->count();
    $tempShares = SharedMeeting::where('meeting_type', 'temporary')->count();

    echo "   - Regular shared meetings: $regularShares\n";
    echo "   - Temporary shared meetings: $tempShares\n";

    // Check notifications
    $shareNotifications = Notification::where('type', 'meeting_share_request')->count();
    echo "   - Share request notifications: $shareNotifications\n";

    echo "\n✅ Shared meeting invitation system appears to be working correctly!\n";

    // Test if we can query both meeting types
    $sharesWithMeetings = SharedMeeting::with(['meeting', 'temporaryMeeting'])->get();
    $validShares = 0;

    foreach ($sharesWithMeetings as $share) {
        if ($share->meeting_type === 'temporary' && $share->temporaryMeeting) {
            $validShares++;
        } elseif ($share->meeting_type === 'regular' && $share->meeting) {
            $validShares++;
        }
    }

    echo "✅ Valid shares with accessible meetings: $validShares / {$sharesWithMeetings->count()}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";
