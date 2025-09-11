<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\GoogleDriveService;

echo "=== Testing User Token Drive Access ===\n";

$sharer = User::where('email', 'fanlee1996@gmail.com')->first();
if (!$sharer || !$sharer->googleToken) {
    echo "No sharer or token found\n";
    exit;
}

try {
    $drive = app(GoogleDriveService::class);
    $token = $sharer->googleToken;

    echo "Token details:\n";
    echo "- Access token length: " . strlen($token->access_token) . "\n";
    echo "- Refresh token present: " . (!empty($token->refresh_token) ? 'YES' : 'NO') . "\n";
    echo "- Created at: " . $token->created_at . "\n";
    echo "- Updated at: " . $token->updated_at . "\n";

    // Try to set the token
    $accessToken = $token->access_token ? json_decode($token->access_token, true) ?: ['access_token' => $token->access_token] : [];
    $drive->setAccessToken($accessToken);

    echo "\nTesting basic Drive access...\n";

    // Try to list files from the user's Drive (basic permission test)
    try {
        $driveService = $drive->getDrive();
        $response = $driveService->files->listFiles([
            'pageSize' => 1,
            'fields' => 'files(id,name)'
        ]);
        echo "Basic Drive access: SUCCESS\n";
        echo "Files found: " . count($response->getFiles()) . "\n";
    } catch (\Throwable $e) {
        echo "Basic Drive access failed: " . $e->getMessage() . "\n";
        echo "Error code: " . $e->getCode() . "\n";
    }

    // Try to get a specific file (the transcript file from our test)
    echo "\nTesting specific file access...\n";
    try {
        $fileId = '1bati3zY9k8y2gOPlB9RwvMSnc0hixi9-'; // transcript file from our test
        $file = $driveService->files->get($fileId, ['fields' => 'id,name,owners']);
        echo "File access: SUCCESS\n";
        echo "File name: " . $file->getName() . "\n";
        echo "File ID: " . $file->getId() . "\n";
        $owners = $file->getOwners();
        if ($owners) {
            echo "Owner: " . $owners[0]->getEmailAddress() . "\n";
        }
    } catch (\Throwable $e) {
        echo "File access failed: " . $e->getMessage() . "\n";
        echo "Error code: " . $e->getCode() . "\n";
    }

} catch (\Throwable $e) {
    echo "Drive service setup failed: " . $e->getMessage() . "\n";
}
