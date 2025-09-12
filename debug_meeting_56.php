<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando reunión con ID 56 ===\n";

$meeting = App\Models\TranscriptionLaravel::find(56);
if ($meeting) {
    echo "✅ Reunión encontrada en transcriptions_laravel:\n";
    echo "  - ID: {$meeting->id}\n";
    echo "  - Meeting name: {$meeting->meeting_name}\n";
    echo "  - Username: {$meeting->username}\n";
    echo "  - Created at: {$meeting->created_at}\n";
    echo "  - Transcript drive ID: " . ($meeting->transcript_drive_id ?: 'NULL') . "\n";
    echo "  - Audio drive ID: " . ($meeting->audio_drive_id ?: 'NULL') . "\n";
    echo "  - Transcript download URL: " . ($meeting->transcript_download_url ?: 'NULL') . "\n";
    echo "  - Audio download URL: " . ($meeting->audio_download_url ?: 'NULL') . "\n";
} else {
    echo "❌ Reunión con ID 56 no encontrada en transcriptions_laravel\n";
}

// Verificar si existe en la tabla Meeting
$newMeeting = App\Models\Meeting::find(56);
if ($newMeeting) {
    echo "✅ Reunión encontrada en meetings (nuevo modelo)\n";
} else {
    echo "❌ Reunión con ID 56 no encontrada en meetings (nuevo modelo)\n";
}

// Verificar usuario actual
$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
if ($user && $meeting) {
    echo "\n=== Verificando acceso ===\n";
    echo "✅ Usuario actual: {$user->username}\n";
    echo "✅ Owner de reunión: {$meeting->username}\n";
    echo "✅ ¿Mismo usuario?: " . ($user->username === $meeting->username ? 'SÍ' : 'NO') . "\n";

    // Verificar acceso compartido
    $sharedAccess = App\Models\SharedMeeting::where('meeting_id', 56)
        ->where('shared_with', $user->id)
        ->where('status', 'accepted')
        ->exists();
    echo "✅ ¿Acceso compartido?: " . ($sharedAccess ? 'SÍ' : 'NO') . "\n";
}
