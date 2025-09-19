<?php
// Simple CLI to test audio -> OGG conversion using the app container
// Usage: php tools/test_audio_conversion.php <input-audio-path>

use App\Services\AudioConversionService;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/test_audio_conversion.php <input-audio-path>\n");
    exit(1);
}

$input = $argv[1];
if (!is_file($input)) {
    fwrite(STDERR, "Input file not found: $input\n");
    exit(1);
}

try {
    $svc = app(AudioConversionService::class);
    echo "Probing FFmpeg...\n";
    // This will throw if ffmpeg isn't callable
    $ref = new ReflectionClass($svc);
    $m = $ref->getMethod('ensureFfmpegAvailable');
    $m->setAccessible(true);
    $m->invoke($svc);
    echo "FFmpeg/ffprobe OK\n";
    echo "Using bins: ffmpeg=" . config('audio.ffmpeg_bin') . ", ffprobe=" . config('audio.ffprobe_bin') . "\n";

    $mime = function_exists('mime_content_type') ? @mime_content_type($input) : null;
    $ext = pathinfo($input, PATHINFO_EXTENSION) ?: null;
    echo "Input: $input\n";
    echo "Mime : " . ($mime ?: 'unknown') . "\n";
    echo "Ext  : " . ($ext ?: 'unknown') . "\n";

    $res = $svc->convertToOgg($input, $mime, $ext);
    echo "Converted: " . ($res['was_converted'] ? 'yes' : 'no (already OGG)') . "\n";
    echo "Output   : {$res['path']}\n";
    echo "Mime     : {$res['mime_type']}\n";

    if ($res['was_converted'] && is_file($res['path'])) {
        $size = filesize($res['path']);
        echo "Output size: $size bytes\n";
    }
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, "Conversion failed: " . $e->getMessage() . "\n");
    exit(2);
}
