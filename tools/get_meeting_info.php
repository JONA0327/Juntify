<?php
// Usage: php tools/get_meeting_info.php <meeting_id>

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$meetingId = (int)($argv[1] ?? 0);
if ($meetingId <= 0) {
    fwrite(STDERR, "Uso: php tools/get_meeting_info.php <meeting_id>\n");
    exit(1);
}

try {
    $row = Illuminate\Support\Facades\DB::table('transcriptions_laravel')
        ->where('id', $meetingId)
        ->first();
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Reunión no encontrada', 'id' => $meetingId], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    }

    $out = [
        'ok' => true,
        'id' => $row->id,
        'username' => $row->username ?? null,
        'meeting_name' => $row->meeting_name ?? null,
        'audio_drive_id' => $row->audio_drive_id ?? null,
        'audio_download_url' => $row->audio_download_url ?? null,
        'transcript_drive_id' => $row->transcript_drive_id ?? null,
        'transcript_download_url' => $row->transcript_download_url ?? null,
        'created_at' => $row->created_at ?? null,
        'updated_at' => $row->updated_at ?? null,
    ];

    echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
