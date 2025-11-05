<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\TranscriptionTemp;
use Illuminate\Support\Facades\Storage;

$email = 'CongresoBNI@gmail.com';
$user = User::where('email', $email)->first();
if (! $user) {
    echo "User not found: $email\n";
    exit(0);
}

echo "User: {$user->id} - {$user->email} - roles={$user->roles} is_role_protected={$user->is_role_protected}\n\n";

$trans = TranscriptionTemp::where('user_id', $user->id)->get();
if ($trans->isEmpty()) {
    echo "No transcriptions found for user\n";
    exit(0);
}

foreach ($trans as $t) {
    echo "ID: {$t->id}\n";
    echo "  meeting_name: {$t->meeting_name}\n";
    echo "  created_at: {$t->created_at}\n";
    echo "  audio_path: {$t->audio_path}\n";
    echo "  transcription_path: {$t->transcription_path}\n";
    echo "  expires_at: {$t->expires_at}\n";
    try {
        $audioExists = $t->audio_path ? Storage::disk('local')->exists($t->audio_path) : false;
        $juExists = $t->transcription_path ? Storage::disk('local')->exists($t->transcription_path) : false;
    } catch (\Exception $e) {
        $audioExists = 'error: ' . $e->getMessage();
        $juExists = 'error: ' . $e->getMessage();
    }
    echo "  audio_exists: " . var_export($audioExists, true) . "\n";
    echo "  ju_exists: " . var_export($juExists, true) . "\n";
    echo "\n";
}

?>
