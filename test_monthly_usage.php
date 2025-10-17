<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MonthlyMeetingUsage;
use App\Services\PlanLimitService;
use App\Models\User;
use Carbon\Carbon;

echo "=== Testing Monthly Meeting Usage System ===\n\n";

// Test with first user
$user = User::first();
if (!$user) {
    echo "No users found to test with.\n";
    exit;
}

echo "Testing with user: {$user->full_name} ({$user->username})\n\n";

// 1. Test getting current usage
$currentUsage = MonthlyMeetingUsage::getCurrentMonthCount($user->id);
echo "1. Current month usage: {$currentUsage} meetings\n";

// 2. Test incrementing usage
echo "2. Incrementing usage...\n";
MonthlyMeetingUsage::incrementUsage($user->id, $user->current_organization_id, [
    'meeting_id' => 'test-123',
    'meeting_name' => 'Test Meeting for Usage System',
    'type' => 'test'
]);

$newUsage = MonthlyMeetingUsage::getCurrentMonthCount($user->id);
echo "   New usage: {$newUsage} meetings\n";

// 3. Test PlanLimitService integration
echo "3. Testing PlanLimitService integration:\n";
$planService = new PlanLimitService();
$limits = $planService->getLimitsForUser($user);

echo "   Role: {$limits['role']}\n";
echo "   Max per month: " . ($limits['max_meetings_per_month'] ?? 'unlimited') . "\n";
echo "   Used this month: {$limits['used_this_month']}\n";
echo "   Remaining: " . ($limits['remaining'] ?? 'unlimited') . "\n";
echo "   Can create another: " . ($planService->canCreateAnotherMeeting($user) ? 'Yes' : 'No') . "\n";

// 4. Test getting usage record with audit log
echo "4. Audit log (last 3 entries):\n";
$usage = MonthlyMeetingUsage::getCurrentMonthUsage($user->id);
$records = array_slice($usage->meeting_records ?? [], -3);
foreach ($records as $record) {
    $timestamp = \Carbon\Carbon::parse($record['timestamp'])->format('Y-m-d H:i:s');
    $action = $record['action'];
    $meetingName = $record['data']['meeting_name'] ?? 'Unknown';
    echo "   {$timestamp} - {$action} - {$meetingName}\n";
}

// 5. Show all users' current usage
echo "\n5. Current month usage for all users:\n";
$allUsage = MonthlyMeetingUsage::where('year', Carbon::now()->year)
    ->where('month', Carbon::now()->month)
    ->where('meetings_consumed', '>', 0)
    ->get();

foreach ($allUsage as $usage) {
    $user = User::find($usage->user_id);
    $userName = $user ? ($user->full_name ?? $user->username) : ($usage->username ?? 'Unknown');
    echo "   {$userName}: {$usage->meetings_consumed} meetings\n";
}

echo "\nTest completed!\n";
