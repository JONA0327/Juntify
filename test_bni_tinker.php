<?php
// Test BNI role limits directly using Laravel

// Check if BNI user exists
$bniUser = App\Models\User::where('email', 'bni.test@juntify.com')->first();

if (!$bniUser) {
    echo "Creating BNI test user...\n";
    $bniUser = App\Models\User::create([
        'id' => (string) Str::uuid(),
        'name' => 'BNI Test User',
        'email' => 'bni.test@juntify.com',
        'email_verified_at' => now(),
        'password' => bcrypt('test123'),
        'roles' => 'bni',
    ]);
}

echo "BNI User: " . $bniUser->email . " (Role: " . $bniUser->roles . ")\n";

// Test PlanLimitService
$service = new App\Services\PlanLimitService();
$limits = $service->getLimitsForUser($bniUser);

echo "Limits:\n";
foreach ($limits as $key => $value) {
    if (is_null($value)) {
        echo "  $key: UNLIMITED\n";
    } else {
        echo "  $key: $value\n";
    }
}

$canCreate = $service->canCreateAnotherMeeting($bniUser);
echo "Can create meeting: " . ($canCreate ? "YES" : "NO") . "\n";

// Test unlimited role detection
if (method_exists($service, 'isUnlimitedRole')) {
    $isUnlimited = $service->isUnlimitedRole($bniUser->roles);
    echo "Is unlimited role: " . ($isUnlimited ? "YES" : "NO") . "\n";
}
?>