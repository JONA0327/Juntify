<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MonthlyMeetingUsage;
use App\Models\TranscriptionLaravel;
use App\Models\TranscriptionTemp;
use Carbon\Carbon;

echo "=== Migrating Existing Meetings to Monthly Usage System ===\n\n";

// Get all meetings and create usage records for the months they were created
$meetings = TranscriptionLaravel::select('id', 'username', 'meeting_name', 'created_at')
    ->orderBy('created_at')
    ->get();

$tempMeetings = TranscriptionTemp::select('id', 'user_id', 'title', 'created_at')
    ->orderBy('created_at')
    ->get();

echo "Found {$meetings->count()} regular meetings and {$tempMeetings->count()} temporary meetings\n\n";

$processed = 0;
$monthlyStats = [];

// Process regular meetings
foreach ($meetings as $meeting) {
    $user = \App\Models\User::where('username', $meeting->username)->first();
    if (!$user) {
        echo "Warning: User not found for username {$meeting->username}\n";
        continue;
    }

    $createdAt = Carbon::parse($meeting->created_at);
    $year = $createdAt->year;
    $month = $createdAt->month;

    // Find or create usage record for that month
    $usage = MonthlyMeetingUsage::firstOrCreate([
        'user_id' => $user->id,
        'username' => $user->username,
        'organization_id' => $user->current_organization_id,
        'year' => $year,
        'month' => $month,
    ], [
        'meetings_consumed' => 0,
        'meeting_records' => []
    ]);

    // Increment and add record
    $usage->increment('meetings_consumed');
    $records = $usage->meeting_records ?? [];
    $records[] = [
        'timestamp' => $meeting->created_at,
        'action' => 'migrated_regular_meeting',
        'data' => [
            'meeting_id' => $meeting->id,
            'meeting_name' => $meeting->meeting_name,
            'type' => 'regular'
        ]
    ];
    $usage->update(['meeting_records' => $records]);

    $processed++;
    $monthKey = "{$year}-{$month}";
    $monthlyStats[$monthKey] = ($monthlyStats[$monthKey] ?? 0) + 1;
}

// Process temporary meetings
foreach ($tempMeetings as $meeting) {
    $createdAt = Carbon::parse($meeting->created_at);
    $year = $createdAt->year;
    $month = $createdAt->month;

    // Find or create usage record for that month
    $usage = MonthlyMeetingUsage::firstOrCreate([
        'user_id' => $meeting->user_id,
        'username' => null, // temp meetings use user_id
        'organization_id' => null, // TODO: get from user if needed
        'year' => $year,
        'month' => $month,
    ], [
        'meetings_consumed' => 0,
        'meeting_records' => []
    ]);

    // Increment and add record
    $usage->increment('meetings_consumed');
    $records = $usage->meeting_records ?? [];
    $records[] = [
        'timestamp' => $meeting->created_at,
        'action' => 'migrated_temporary_meeting',
        'data' => [
            'meeting_id' => $meeting->id,
            'meeting_name' => $meeting->title,
            'type' => 'temporary'
        ]
    ];
    $usage->update(['meeting_records' => $records]);

    $processed++;
    $monthKey = "{$year}-{$month}";
    $monthlyStats[$monthKey] = ($monthlyStats[$monthKey] ?? 0) + 1;
}

echo "Migration completed!\n";
echo "Processed: {$processed} meetings\n\n";

echo "Monthly breakdown:\n";
ksort($monthlyStats);
foreach ($monthlyStats as $month => $count) {
    echo "  {$month}: {$count} meetings\n";
}

echo "\nCurrent month usage summary:\n";
$currentMonth = Carbon::now();
$currentUsage = MonthlyMeetingUsage::where('year', $currentMonth->year)
    ->where('month', $currentMonth->month)
    ->get();

foreach ($currentUsage as $usage) {
    $user = \App\Models\User::find($usage->user_id);
    $userName = $user ? ($user->full_name ?? $user->username) : $usage->username;
    echo "  {$userName}: {$usage->meetings_consumed} meetings\n";
}

echo "\nDone!\n";
