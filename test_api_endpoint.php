<?php

// Simple test to check if the respond endpoint is accessible
$baseUrl = 'http://juntify.test'; // Adjust this to your local domain

// Test data - you'll need to replace these with real values from your database
$testData = [
    'shared_meeting_id' => 1, // Replace with an actual shared_meeting ID
    'action' => 'accept',
    'notification_id' => 1 // Replace with an actual notification ID
];

$postData = json_encode($testData);

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/shared-meetings/respond');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

echo "Testing /api/shared-meetings/respond endpoint...\n";
echo "Request data: " . $postData . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";
if ($error) {
    echo "cURL Error: " . $error . "\n";
}
echo "Response: " . $response . "\n";
