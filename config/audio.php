<?php
return [
    // Fuerza conversión de cualquier audio subido o grabado a OGG (Opus)
    'force_ogg' => env('AUDIO_FORCE_OGG', true),

    // Bitrate objetivo para libopus (puedes ajustar 64k, 96k, 128k, etc.)
    'opus_bitrate' => env('AUDIO_OPUS_BITRATE', '96k'),

    // Timeout (segundos) para procesos ffmpeg largos
    'conversion_timeout' => env('AUDIO_CONVERSION_TIMEOUT', 600),

    // Rutas de binarios (opcional). Útil en Windows si no están en el PATH.
    // Ejemplo en .env:
    // FFMPEG_BIN="C:\\ffmpeg\\bin\\ffmpeg.exe"
    // FFPROBE_BIN="C:\\ffmpeg\\bin\\ffprobe.exe"
    'ffmpeg_bin' => env('FFMPEG_BIN', 'ffmpeg'),
    'ffprobe_bin' => env('FFPROBE_BIN', 'ffprobe'),

    // Usar script Python para convertir a OGG en vez de ejecutar ffmpeg directo desde PHP
    'use_python_script' => env('AUDIO_USE_PYTHON', false),

    // === NUEVAS CONFIGURACIONES PARA ARCHIVOS GRANDES ===
    
    // Límite de memoria para procesamiento de audio
    'process_memory_limit' => env('AUDIO_PROCESS_MEMORY_LIMIT', '512M'),
    
    // Tamaño máximo de archivo (en bytes) - 50MB por defecto
    'max_file_size' => env('AUDIO_MAX_SIZE', 52428800),
    
    // Modo de procesamiento para chunks: 'sync', 'async', 'queue'
    'chunked_processing_mode' => env('AUDIO_CHUNKED_PROCESSING', 'async'),
    
    // Habilitar sistema de colas para audio grande
    'queue_enabled' => env('AUDIO_QUEUE_ENABLED', true),
    
    // Nombre de la cola para procesamiento de audio
    'queue_name' => env('AUDIO_QUEUE_NAME', 'audio-processing'),
    // Binario de Python
    'python_bin' => env('PYTHON_BIN', 'python3'),

    // Modo de procesamiento para transcripciones fragmentadas: "sync" procesa en la
    // misma petición HTTP (útil en entornos donde no corre un worker de colas) y
    // "queue" delega a un job asíncrono. Por defecto usamos "sync" para evitar
    // que las cargas queden detenidas indefinidamente si no hay worker.
    'chunked_processing_mode' => env('AUDIO_CHUNKED_PROCESSING', 'sync'),
];
