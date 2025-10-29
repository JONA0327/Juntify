#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== BNI ROLE UNLIMITED LIMITS TEST ===\n\n";

// Get BNI user
$bniUser = App\Models\User::where('email', 'bni.test@juntify.com')->first();

if (!$bniUser) {
    echo "❌ BNI user not found!\n";
    exit(1);
}

echo "✅ Found BNI user: {$bniUser->email}\n";
echo "   Role: {$bniUser->roles}\n\n";

// Test PlanLimitService
$planLimitService = new App\Services\PlanLimitService();

// Get limits for user
$limits = $planLimitService->getLimitsForUser($bniUser);

echo "=== USER LIMITS ===\n";
foreach ($limits as $key => $value) {
    if (is_null($value)) {
        echo "✅ {$key}: UNLIMITED (null)\n";
    } elseif (is_bool($value)) {
        echo "   {$key}: " . ($value ? 'true' : 'false') . "\n";
    } else {
        echo "   {$key}: {$value}\n";
    }
}

// Test if can create meetings
$canCreate = $planLimitService->canCreateAnotherMeeting($bniUser);
echo "\n=== MEETING CREATION ===\n";
echo ($canCreate ? "✅" : "❌") . " Can create meeting: " . ($canCreate ? "YES" : "NO") . "\n";

// Check if unlimited
$isUnlimited = is_null($limits['max_meetings_per_month']);
echo "\n=== SUMMARY ===\n";
echo ($isUnlimited ? "✅" : "❌") . " Has unlimited meetings: " . ($isUnlimited ? "YES" : "NO") . "\n";
echo ($canCreate ? "✅" : "❌") . " Can create meetings: " . ($canCreate ? "YES" : "NO") . "\n";

if ($isUnlimited && $canCreate) {
    echo "\n🎉 SUCCESS! BNI role has unlimited limits!\n";
} else {
    echo "\n❌ FAILED! BNI role does not have unlimited limits.\n";
}
