<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProcessChunkedTranscription;
use App\Services\AudioConversionService;

class TranscriptionController extends Controller
{
    private AudioConversionService $audioConversionService;

    public function __construct(AudioConversionService $audioConversionService)
    {
        $this->audioConversionService = $audioConversionService;
    }

    /**
     * Optimiza la configuración de transcripción para mejor detección automática de hablantes
     * Permite que AssemblyAI detecte automáticamente sin ser demasiado sensible
     */
    private function getOptimizedConfigForFormat($mimeType, $isMP4, $isMP3, $baseConfig)
    {
        // Para archivos MP3, usar configuración balanceada para detección automática
        if ($isMP3) {
            $mp3Config = $baseConfig;
            $mp3Config['speaker_labels'] = true;        // Activar detección de hablantes
            $mp3Config['format_text'] = false;          // Desactivado para mejor speaker detection
            $mp3Config['speed_boost'] = false;          // Sin speed boost para asegurar calidad
            $mp3Config['speech_threshold'] = 0.4;       // Menos sensible para evitar falsos positivos
            $mp3Config['dual_channel'] = false;         // MP3 grabaciones son mono/stereo mixto
            // Remover speakers_expected para permitir detección automática
            unset($mp3Config['speakers_expected']);

            Log::info('Applied MP3 AUTO-DETECTION config for natural speaker detection', [
                'speaker_labels' => $mp3Config['speaker_labels'],
                'format_text' => $mp3Config['format_text'],
                'speed_boost' => $mp3Config['speed_boost'],
                'speech_threshold' => $mp3Config['speech_threshold'],
                'speakers_expected' => 'AUTO (not forced)',
            ]);

            return $mp3Config;
        }

        // Para archivos MP4, usar configuración balanceada para detección automática
        if ($isMP4) {
            $mp4Config = $baseConfig;
            $mp4Config['speaker_labels'] = true;        // Activar detección de hablantes
            $mp4Config['format_text'] = false;          // Desactivado para mejor speaker detection
            $mp4Config['speed_boost'] = false;          // Sin speed boost para máxima calidad
            $mp4Config['speech_threshold'] = 0.4;       // Menos sensible para evitar falsos positivos
            $mp4Config['dual_channel'] = false;         // Forzar mono-análisis para mejor detección
            // Remover speakers_expected para permitir detección automática
            unset($mp4Config['speakers_expected']);

            Log::info('Applied MP4 AUTO-DETECTION config for natural speaker detection', [
                'speaker_labels' => $mp4Config['speaker_labels'],
                'format_text' => $mp4Config['format_text'],
                'speed_boost' => $mp4Config['speed_boost'],
                'speech_threshold' => $mp4Config['speech_threshold'],
                'speakers_expected' => 'AUTO (not forced)',
            ]);

            return $mp4Config;
        }

        // Para otros formatos, usar configuración estándar con detección automática
        return $baseConfig;
    }

    public function store(Request $request)
    {
        $acceptedFormats = ['mp3', 'wav', 'm4a', 'flac', 'ogg', 'aac', 'webm'];

        $request->validate([
            'audio'    => 'required|file|max:102400|mimes:' . implode(',', array_merge($acceptedFormats, ['mp4'])), // Máximo 100MB
            'language' => 'nullable|in:es,en,fr,de',
        ], [
            'audio.mimes' => 'Este sistema solo acepta archivos de audio en formato MP3, WAV, M4A (incluye MP4), FLAC, OGG, AAC o WebM.',
        ]);

        $file = $request->file('audio');

        $mimeType = $file->getMimeType();
        $fileName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        $isWebM = $extension === 'webm' || ($mimeType && strpos($mimeType, 'webm') !== false);

        $isMP4 = strpos($mimeType, 'mp4') !== false ||
                 strpos($fileName, '.m4a') !== false;

        $isMP3 = strpos($mimeType, 'mpeg') !== false ||
                 strpos($fileName, '.mp3') !== false;

        $isLargeAudio = $isMP4 || $isMP3 || in_array($extension, $acceptedFormats, true);

        if ($isLargeAudio) {
            Log::info('Archivo de audio aceptado', [
                'tipo' => $mimeType,
                'nombre' => $fileName,
                'es_mp4' => $isMP4,
                'es_mp3' => $isMP3,
                'tamaño' => $file->getSize(),
                'formato_original' => strtoupper($extension ?: 'DESCONOCIDO'),
                'formatos_aceptados' => ['MP3', 'WAV', 'M4A', 'FLAC', 'OGG', 'AAC', 'WebM'],
            ]);
            set_time_limit(7200); // 2 horas para archivos grandes
        } else {
            set_time_limit(600); // 10 minutos para otros formatos
        }

        $filePath = $file->getRealPath();

        $language = $request->input('language', 'es');
        $apiKey   = config('services.assemblyai.api_key');

        // Log información del archivo
        Log::info('Processing audio file', [
            'original_name' => $fileName,
            'size' => $file->getSize(),
            'mime_type' => $mimeType,
            'language' => $language,
            'is_webm' => $isWebM,
            'is_mp3' => $isMP3,
            'formato_original' => strtoupper($extension ?: 'DESCONOCIDO'),
            'formatos_aceptados' => ['MP3', 'WAV', 'M4A', 'FLAC', 'OGG', 'AAC', 'WebM'],
        ]);

        // Log específico para archivos grandes
        if ($isLargeAudio) {
            Log::info('Archivo de audio grande detectado - aplicando optimizaciones para archivos largos', [
                'file_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                'original_name' => $fileName,
                'timeout_seconds' => 7200,
                'formato' => strtoupper($extension ?: 'DESCONOCIDO'),
                'expected_processing_time' => '1+ hours for long files',
            ]);
        }

        try {
            $conversionResult = $this->audioConversionService->convertToMp3($filePath, $mimeType, $extension);
        } catch (\Throwable $e) {
            Log::error('Audio conversion to MP3 failed', [
                'error' => $e->getMessage(),
                'mime_type' => $mimeType,
                'extension' => $extension,
            ]);

            return response()->json([
                'error' => 'No se pudo convertir el audio a MP3.',
                'details' => $e->getMessage(),
            ], 500);
        }

        $filePath = $conversionResult['path'];
        $mimeType = $conversionResult['mime_type'] ?? 'audio/mpeg';
        $isMP3 = true;
        $isMP4 = false;
        $isLargeAudio = true;

        if (($conversionResult['was_converted'] ?? false) === true) {
            register_shutdown_function(static function () use ($filePath) {
                if (is_string($filePath) && file_exists($filePath)) {
                    @unlink($filePath);
                }
            });
        }

        if (empty($apiKey)) {
            $failedPath = $this->saveFailedAudio($filePath);
            return response()->json([
                'error'          => 'AssemblyAI API key missing',
                'failed_audio'   => $failedPath,
            ], 500);
        }

        $audioData = file_get_contents($filePath);
        if ($audioData === false) {
            Log::error('Failed to read audio file', ['file_path' => $filePath]);
            $failedPath = $this->saveFailedAudio($filePath);
            return response()->json([
                'error'        => 'Failed to read audio file',
                'failed_audio' => $failedPath,
            ], 500);
        }

        Log::info('Audio file loaded', [
            'size_bytes' => strlen($audioData),
            'was_converted_to_mp3' => $conversionResult['was_converted'] ?? false,
        ]);

        try {
            // Ajustar timeouts específicamente para archivos de audio grandes
            $timeout = $isLargeAudio ?
                config('services.assemblyai.timeout', 3600) : // 1 hora para archivos grandes
                config('services.assemblyai.timeout', 300);  // 5 minutos para otros
            $connectTimeout = config('services.assemblyai.connect_timeout', 60);

            $http = Http::timeout($timeout)->connectTimeout($connectTimeout)
                ->withHeaders([
                    'authorization' => $apiKey,
                    'content-type'  => 'application/octet-stream',
                ])
                ->withBody($audioData, 'application/octet-stream');

            if (!config('services.assemblyai.verify_ssl', true)) {
                $http = $http->withoutVerifying();
            } else {
                $http = $http->withOptions(['verify' => config('services.assemblyai.verify_ssl', true)]);
            }

            $response = $http->post('https://api.assemblyai.com/v2/upload');

            Log::info('Upload response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'timeout_used' => $timeout,
                'is_webm' => $isWebM,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $failedPath = $this->saveFailedAudio($filePath);
            Log::error('AssemblyAI upload connection failed', [
                'error' => $e->getMessage(),
                'file_size' => strlen($audioData),
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);
            return response()->json([
                'error'        => 'Failed to connect to AssemblyAI',
                'details'      => $e->getMessage(),
                'failed_audio' => $failedPath,
            ], 504);
        } catch (RequestException $e) {
            $failedPath = $this->saveFailedAudio($filePath);
            Log::error('AssemblyAI upload SSL error', [
                'error' => $e->getMessage(),
                'verify_ssl' => config('services.assemblyai.verify_ssl', true),
            ]);
            return response()->json([
                'error'        => 'SSL certificate validation failed',
                'details'      => $e->getMessage(),
                'failed_audio' => $failedPath,
            ], 500);
        }

        if (!$response->successful()) {
            Log::error('Upload failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            $failedPath = $this->saveFailedAudio($filePath);
            return response()->json([
                'error'        => 'Upload failed',
                'details'      => $response->json(),
                'failed_audio' => $failedPath,
            ], 500);
        }

        $uploadUrl = $response->json('upload_url');
        Log::info('Upload successful', ['upload_url' => $uploadUrl]);

        try {
            // Ajustar timeouts específicamente para archivos de audio grandes
            $timeout = $isLargeAudio ?
                config('services.assemblyai.timeout', 3600) : // 1 hora para archivos grandes
                config('services.assemblyai.timeout', 300);  // 5 minutos para otros
            $connectTimeout = config('services.assemblyai.connect_timeout', 60);

            $transcriptionHttp = Http::timeout($timeout)->connectTimeout($connectTimeout)
                ->withHeaders([
                    'authorization' => $apiKey,
                ]);

            if (!config('services.assemblyai.verify_ssl', true)) {
                $transcriptionHttp = $transcriptionHttp->withoutVerifying();
            } else {
                $transcriptionHttp = $transcriptionHttp->withOptions(['verify' => config('services.assemblyai.verify_ssl', true)]);
            }

            $baseConfig = [
                'audio_url'                => $uploadUrl,
                'language_code'           => $language,
                'punctuate'               => true,
                'format_text'             => false,  // Desactivado para mejor speaker detection
                'dual_channel'            => false,
                'speaker_labels'          => true,
                // Permitir detección automática - no forzar número específico
                'speech_threshold'        => 0.4,    // Balanceado para evitar falsos positivos
                'speed_boost'             => false,
                'auto_highlights'         => false,
                'content_safety'          => false,
                'iab_categories'          => false,
                'sentiment_analysis'      => false,
                'entity_detection'        => false,
                'filter_profanity'        => false,
                'redact_pii'              => false,
            ];

            // Aplicar optimizaciones según el formato de audio
            $transcriptionConfig = $this->getOptimizedConfigForFormat($mimeType, $isMP4, $isMP3, $baseConfig);

            $transcription = $transcriptionHttp->post('https://api.assemblyai.com/v2/transcript', $transcriptionConfig);

            Log::info('Transcription request sent', [
                'status' => $transcription->status(),
                'successful' => $transcription->successful(),
                'timeout_used' => $timeout,
                'is_webm' => $isWebM,
                'config_applied' => $isWebM ? 'WebM optimizations' : 'Standard config',
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $failedPath = $this->saveFailedAudio($filePath);
            Log::error('AssemblyAI transcription connection failed', [
                'error' => $e->getMessage(),
                'upload_url' => $uploadUrl,
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);
            return response()->json([
                'error'        => 'Failed to connect to AssemblyAI for transcription request',
                'details'      => $e->getMessage(),
                'failed_audio' => $failedPath,
            ], 504);
        } catch (RequestException $e) {
            $failedPath = $this->saveFailedAudio($filePath);
            Log::error('AssemblyAI transcription SSL error', [
                'error' => $e->getMessage(),
                'verify_ssl' => config('services.assemblyai.verify_ssl', true),
            ]);
            return response()->json([
                'error'        => 'SSL certificate validation failed for transcription request',
                'details'      => $e->getMessage(),
                'failed_audio' => $failedPath,
            ], 500);
        }

        if (!$transcription->successful()) {
            Log::error('Transcription request failed', [
                'status' => $transcription->status(),
                'response' => $transcription->json(),
            ]);
            $failedPath = $this->saveFailedAudio($filePath);
            return response()->json([
                'error'        => 'Transcription request failed',
                'details'      => $transcription->json(),
                'failed_audio' => $failedPath,
            ], 500);
        }

        $transcriptId = $transcription->json('id');
        Log::info('Transcription started successfully', ['transcript_id' => $transcriptId]);

        return response()->json(['id' => $transcriptId]);
    }

    private function saveFailedAudio(string $filePath): ?string
    {
        try {
            $extension    = pathinfo($filePath, PATHINFO_EXTENSION);
            $filename     = Str::uuid() . ($extension ? '.' . $extension : '');
            $relativePath = 'failed-audio/' . $filename;
            Storage::put($relativePath, file_get_contents($filePath));
            return storage_path('app/' . $relativePath);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function show(string $id)
    {
        $cacheKey = "chunked_transcription:{$id}";
        if (Cache::has($cacheKey)) {
            $data = Cache::get($cacheKey);

            if (isset($data['error'])) {
                return response()->json([
                    'status' => 'error',
                    'error' => $data['error'],
                ]);
            }

            if (!isset($data['transcription_id'])) {
                return response()->json([
                    'status' => $data['status'] ?? 'queued',
                ]);
            }

            $id = $data['transcription_id'];
        }

        $apiKey = config('services.assemblyai.api_key');

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
            ])
                ->timeout(120) // 2 minutos de timeout para respuestas grandes
                ->connectTimeout(30) // 30 segundos para conectar
                ->get("https://api.assemblyai.com/v2/transcript/{$id}");

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'Status check failed',
                    'details' => $response->json(),
                ], 500);
            }

            return $response->json();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AssemblyAI connection timeout', [
                'transcript_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Connection timeout with AssemblyAI',
                'message' => 'La respuesta de AssemblyAI está tardando mucho. Intenta de nuevo en unos momentos.',
                'transcript_id' => $id
            ], 504); // Gateway timeout
        } catch (\Exception $e) {
            Log::error('AssemblyAI check failed', [
                'transcript_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'Unexpected error during status check',
                'message' => 'Error inesperado al verificar el estado de la transcripción.',
                'transcript_id' => $id
            ], 500);
        }
    }

    public function initChunkedUpload(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'size' => 'required|integer|min:1',
            'language' => 'nullable|in:es,en,fr,de',
            'chunks' => 'required|integer|min:1'
        ]);

        $uploadId = Str::uuid();
        $language = $request->input('language', 'es');
        $filename = $request->input('filename');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $acceptedFormats = [
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'flac' => 'audio/flac',
            'ogg'  => 'audio/ogg',
            'aac'  => 'audio/aac',
            'webm' => 'audio/webm',
        ];

        if (!array_key_exists($extension, $acceptedFormats)) {
            Log::warning('Invalid audio format in chunked upload', [
                'filename' => $filename,
                'extension' => $extension,
                'accepted_extensions' => array_keys($acceptedFormats),
            ]);

            return response()->json([
                'error' => 'Formato de audio no soportado',
                'message' => 'Este sistema acepta archivos MP3, WAV, M4A, FLAC, OGG, AAC o WebM.',
                'accepted_formats' => array_keys($acceptedFormats),
            ], 422);
        }

        Log::info('Valid audio format detected for chunked upload', [
            'filename' => $filename,
            'extension' => $extension,
            'mime_type' => $acceptedFormats[$extension],
            'size_mb' => round($request->input('size') / 1024 / 1024, 2),
            'chunks' => $request->input('chunks'),
        ]);
        $chunks = $request->input('chunks');

        // Crear directorio temporal para los chunks
        $uploadDir = storage_path("app/temp-uploads/{$uploadId}");
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Crear metadatos del upload
        $metadata = [
            'upload_id' => $uploadId,
            'filename' => $request->input('filename'),
            'original_extension' => $extension,
            'original_mime_type' => $acceptedFormats[$extension] ?? null,
            'conversion_target' => 'mp3',
            'conversion_required' => $extension !== 'mp3',
            'total_size' => $request->input('size'),
            'language' => $language,
            'chunks_expected' => $chunks,
            'chunks_received' => 0,
            'created_at' => now()->toISOString()
        ];

        file_put_contents(
            storage_path("app/temp-uploads/{$uploadId}/metadata.json"),
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Crear URLs para cada chunk (en este caso, solo necesitamos el endpoint)
        $chunkUrls = [];
        for ($i = 0; $i < $chunks; $i++) {
            $chunkUrls[] = "/transcription/chunked/upload";
        }

        Log::info('Chunked upload initialized', [
            'upload_id' => $uploadId,
            'filename' => $request->input('filename'),
            'size' => $request->input('size'),
            'chunks' => $chunks,
            'language' => $language
        ]);

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_urls' => $chunkUrls
        ]);
    }

    public function uploadChunk(Request $request)
    {
        // Aumentar tiempo límite para chunks grandes
        set_time_limit(300); // 5 minutos por chunk

        $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'upload_id' => 'required|string'
        ]);

        $uploadId = $request->input('upload_id');
        $chunkIndex = $request->input('chunk_index');
        $chunk = $request->file('chunk');

        $uploadDir = storage_path("app/temp-uploads/{$uploadId}");
        $metadataPath = "{$uploadDir}/metadata.json";

        if (!file_exists($metadataPath)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        // Obtener el tamaño ANTES de mover el archivo
        $chunkSize = $chunk->getSize();

        // Guardar el chunk
        $chunkPath = "{$uploadDir}/chunk_{$chunkIndex}";
        $chunk->move($uploadDir, "chunk_{$chunkIndex}");

        $metadataHandle = fopen($metadataPath, 'c+');
        if ($metadataHandle === false) {
            Log::error('Failed to open upload metadata for writing', [
                'upload_id' => $uploadId,
                'metadata_path' => $metadataPath,
            ]);

            return response()->json([
                'error' => 'Failed to update upload metadata',
            ], 500);
        }

        try {
            if (!flock($metadataHandle, LOCK_EX)) {
                Log::error('Failed to acquire lock for upload metadata', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            rewind($metadataHandle);
            $metadataContents = stream_get_contents($metadataHandle);

            if ($metadataContents === false) {
                Log::error('Failed to read upload metadata before update', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            try {
                $metadata = json_decode($metadataContents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::error('Upload metadata contains invalid JSON', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            $metadata['chunks_received']++;

            try {
                $encodedMetadata = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::error('Failed to encode upload metadata as JSON', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            rewind($metadataHandle);
            if (!ftruncate($metadataHandle, 0)) {
                Log::error('Failed to truncate upload metadata file before writing', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            if (fwrite($metadataHandle, $encodedMetadata) === false) {
                Log::error('Failed to write upload metadata to disk', [
                    'upload_id' => $uploadId,
                    'metadata_path' => $metadataPath,
                ]);

                return response()->json([
                    'error' => 'Failed to update upload metadata',
                ], 500);
            }

            fflush($metadataHandle);
        } finally {
            flock($metadataHandle, LOCK_UN);
            fclose($metadataHandle);
        }

        Log::info('Chunk uploaded', [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'chunk_size' => $chunkSize,
            'chunks_received' => $metadata['chunks_received'],
            'chunks_expected' => $metadata['chunks_expected']
        ]);

        return response()->json(['success' => true]);
    }

    public function finalizeChunkedUpload(Request $request)
    {
        // Tiempo límite extendido para procesar archivo grande
        set_time_limit(900); // 15 minutos

        $request->validate([
            'upload_id' => 'required|string'
        ]);

        $uploadId = $request->input('upload_id');
        $uploadDir = storage_path("app/temp-uploads/{$uploadId}");
        $metadataPath = "{$uploadDir}/metadata.json";

        if (!file_exists($metadataPath)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        $metadata = json_decode(file_get_contents($metadataPath), true);

        // Verificar que todos los chunks están presentes
        if ($metadata['chunks_received'] !== $metadata['chunks_expected']) {
            return response()->json([
                'error' => 'Missing chunks',
                'received' => $metadata['chunks_received'],
                'expected' => $metadata['chunks_expected']
            ], 400);
        }

        $trackingId = (string) Str::uuid();
        Cache::put("chunked_transcription:{$trackingId}", [
            'status' => 'queued',
        ]);

        ProcessChunkedTranscription::dispatch($uploadId, $trackingId);

        return response()->json([
            'tracking_id' => $trackingId,
        ], 202);
    }

    private function processLargeAudioFile($filePath, $language, $mimeType = '', $isMP4 = false, $isMP3 = false)
    {
        $apiKey = config('services.assemblyai.api_key');

        if (empty($apiKey)) {
            throw new \Exception('AssemblyAI API key missing');
        }

        // Si no se pasó explícitamente, detectar formato por extensión de archivo
        if (!$isMP4 && !$isMP3) {
            $lowerPath = strtolower($filePath);
            $isMP4 = strpos($lowerPath, '.m4a') !== false;
            $isMP3 = strpos($lowerPath, '.mp3') !== false;
        }

        $isLargeAudio = $isMP4 || $isMP3;

        Log::info('Processing large audio file', [
            'file_path' => basename($filePath),
            'mime_type' => $mimeType,
            'is_mp4' => $isMP4,
            'is_mp3' => $isMP3,
            'is_large_audio' => $isLargeAudio,
            'language' => $language,
        ]);

        // Configuración optimizada para archivos grandes
        $timeout = $isLargeAudio ?
            (int) config('services.assemblyai.timeout', 3600) : // 1 hora para archivos grandes
            (int) config('services.assemblyai.timeout', 300);   // 5 minutos para otros
        $connectTimeout = (int) config('services.assemblyai.connect_timeout', 60);

        Log::info('Starting large file upload to AssemblyAI', [
            'file_size' => filesize($filePath),
            'language' => $language,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout
        ]);

        // Subir archivo a AssemblyAI
        $audioData = file_get_contents($filePath);
        $uploadResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/octet-stream'
        ])
        ->timeout($timeout)
        ->connectTimeout($connectTimeout)
        ->withOptions([
            'verify' => false, // Deshabilitar verificación SSL si hay problemas
            'stream' => true,  // Stream para archivos grandes
        ])
        ->withBody($audioData)
        ->post('https://api.assemblyai.com/v2/upload');

        if (!$uploadResponse->successful()) {
            $error = $uploadResponse->json();
            Log::error('AssemblyAI upload failed', [
                'status' => $uploadResponse->status(),
                'error' => $error
            ]);
            throw new \Exception('AssemblyAI upload failed: ' . json_encode($error));
        }

        $audioUrl = $uploadResponse->json()['upload_url'];

        // Crear transcripción
        $supportsExtras = $language === 'en';

        $basePayload = [
            'audio_url'               => $audioUrl,
            'language_code'           => $language,
            'punctuate'               => true,       // Mantiene puntuación
            'format_text'             => false,      // Desactivado para mejor speaker detection
            'dual_channel'            => false,      // Force mono analysis
            'speaker_labels'          => true,
            'speakers_expected'       => 4,          // Forzar detección de múltiples speakers
            'speech_threshold'        => 0.1,        // Ultra sensible
            'speed_boost'             => false,
            'auto_highlights'         => false,      // Disable to improve speaker detection
            'content_safety'          => false,      // Disable to improve performance
            'iab_categories'          => false,      // Disable to improve performance
            'sentiment_analysis'      => false,      // Disable to improve performance
            'entity_detection'        => false,      // Disable to improve performance
            'filter_profanity'        => false,
            'redact_pii'              => false,
        ];

        // Aplicar optimizaciones según el formato de audio
        $payload = $this->getOptimizedConfigForFormat($mimeType, $isMP4, $isMP3, $basePayload);

        if ($supportsExtras) {
            $payload['auto_chapters'] = true;
            $payload['summarization'] = true;
            $payload['summary_model'] = 'informative';
            $payload['summary_type'] = 'bullets';
        } else {
            Log::info('AssemblyAI extras disabled due to unsupported language', [
                'language' => $language,
            ]);
        }

        $transcriptResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json'
        ])
        ->timeout(60) // Timeout más corto para la creación de transcripción
        ->post('https://api.assemblyai.com/v2/transcript', $payload);

        if (!$transcriptResponse->successful()) {
            $error = $transcriptResponse->json();
            Log::error('AssemblyAI transcript creation failed', [
                'status' => $transcriptResponse->status(),
                'error' => $error
            ]);
            throw new \Exception('AssemblyAI transcript creation failed: ' . json_encode($error));
        }

        $transcriptionId = $transcriptResponse->json()['id'];

        Log::info('Large file transcription started successfully', [
            'transcription_id' => $transcriptionId,
            'audio_url' => $audioUrl
        ]);

        return $transcriptionId;
    }

    private function cleanupTempFiles($uploadDir)
    {
        try {
            if (is_dir($uploadDir)) {
                // Eliminar todos los archivos en el directorio
                $files = glob($uploadDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                // Eliminar el directorio
                rmdir($uploadDir);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to cleanup temp files', [
                'upload_dir' => $uploadDir,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica el estado de una transcripción en AssemblyAI y retorna todos los datos
     * Útil para debugging de detección de hablantes
     */
    public function checkTranscription($id)
    {
        try {
            Log::info('Checking transcription details for debugging', ['transcription_id' => $id]);

            $apiKey = config('services.assemblyai.api_key');
            if (empty($apiKey)) {
                Log::error('AssemblyAI API key is missing when checking transcription', [
                    'transcription_id' => $id,
                ]);

                return response()->json([
                    'error' => 'AssemblyAI API key is not configured.',
                ], 500);
            }

            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'content-type' => 'application/json',
            ])->get("https://api.assemblyai.com/v2/transcript/{$id}");

            if ($response->failed()) {
                Log::error('Failed to retrieve transcription details', [
                    'transcription_id' => $id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return response()->json([
                    'error' => 'Failed to retrieve transcription',
                    'status_code' => $response->status(),
                    'details' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // Enriquecer con análisis adicional para debugging
            if (isset($data['utterances']) && is_array($data['utterances'])) {
                $speakerAnalysis = $this->analyzeSpeakers($data['utterances']);
                $data['speaker_analysis'] = $speakerAnalysis;

                Log::info('Speaker analysis completed', [
                    'transcription_id' => $id,
                    'unique_speakers' => $speakerAnalysis['unique_speakers'],
                    'total_utterances' => count($data['utterances']),
                    'speaker_distribution' => $speakerAnalysis['speaker_stats']
                ]);
            }

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Exception checking transcription', [
                'transcription_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analiza los speakers de las utterances para debugging
     */
    private function analyzeSpeakers(array $utterances)
    {
        $speakerStats = [];
        $uniqueSpeakers = [];

        foreach ($utterances as $utterance) {
            $speaker = $utterance['speaker'] ?? 'Unknown';

            if (!in_array($speaker, $uniqueSpeakers)) {
                $uniqueSpeakers[] = $speaker;
            }

            if (!isset($speakerStats[$speaker])) {
                $speakerStats[$speaker] = [
                    'count' => 0,
                    'total_duration_ms' => 0,
                    'first_appearance_ms' => $utterance['start'] ?? 0,
                    'last_appearance_ms' => $utterance['end'] ?? 0,
                    'avg_confidence' => 0,
                    'confidence_sum' => 0
                ];
            }

            $speakerStats[$speaker]['count']++;
            $speakerStats[$speaker]['total_duration_ms'] += ($utterance['end'] ?? 0) - ($utterance['start'] ?? 0);
            $speakerStats[$speaker]['last_appearance_ms'] = $utterance['end'] ?? 0;

            if (isset($utterance['confidence'])) {
                $speakerStats[$speaker]['confidence_sum'] += $utterance['confidence'];
                $speakerStats[$speaker]['avg_confidence'] = $speakerStats[$speaker]['confidence_sum'] / $speakerStats[$speaker]['count'];
            }
        }

        return [
            'unique_speakers' => count($uniqueSpeakers),
            'speaker_list' => $uniqueSpeakers,
            'speaker_stats' => $speakerStats,
            'total_utterances' => count($utterances)
        ];
    }
}
