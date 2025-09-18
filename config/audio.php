<?php
return [
    // Fuerza conversión de cualquier audio subido o grabado a OGG (Opus)
    'force_ogg' => env('AUDIO_FORCE_OGG', true),

    // Bitrate objetivo para libopus (puedes ajustar 64k, 96k, 128k, etc.)
    'opus_bitrate' => env('AUDIO_OPUS_BITRATE', '96k'),

    // Timeout (segundos) para procesos ffmpeg largos
    'conversion_timeout' => env('AUDIO_CONVERSION_TIMEOUT', 1800),
];
