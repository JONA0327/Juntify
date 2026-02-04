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
use Illuminate\Support\Facades\Validator;

class TranscriptionController extends Controller
{
    /**
     * Optimiza la configuración de transcripción para mejor detección automática de hablantes
     * Permite que AssemblyAI detecte automáticamente sin ser demasiado sensible
     */
    private function getOptimizedConfigForFormat(string $mimeType, array $baseConfig): array
    {
        $config = $baseConfig;
        $config['speaker_labels'] = true;
        $config['format_text'] = false;
        $config['speed_boost'] = false;
        $config['speech_threshold'] = $config['speech_threshold'] ?? 0.4;
        $config['dual_channel'] = $config['dual_channel'] ?? false;
        unset($config['speakers_expected']);

        $isOgg = str_contains(strtolower($mimeType), 'ogg');

        Log::info('Applied neutral audio transcription config', [
            'mime_type' => $mimeType,
            'speaker_labels' => $config['speaker_labels'],
            'format_text' => $config['format_text'],
            'speed_boost' => $config['speed_boost'],
            'speech_threshold' => $config['speech_threshold'],
            'dual_channel' => $config['dual_channel'],
            'language_code' => $config['language_code'] ?? null,
            'is_ogg' => $isOgg,
        ]);

        return $config;
    }

    private function resolveLanguage(?string $language): string
    {
        $allowedLanguages = ['es', 'en', 'fr', 'de'];

        if (!in_array($language, $allowedLanguages, true)) {
            return 'es';
        }

        return $language;
    }

    public function store(Request $request)
    {
        $request->validate([
            'audio'    => 'required|file|max:204800', // Máximo 200MB
            'language' => 'nullable|in:es,en,fr,de',
        ]);

        $file = $request->file('audio');

        $mimeType = strtolower($file->getMimeType());
        $fileName = $file->getClientOriginalName();
        $normalizedName = strtolower($fileName);

        $isWebM = str_contains($mimeType, 'webm') || str_contains($normalizedName, '.webm');
        $isMP4  = str_contains($mimeType, 'mp4') || str_contains($normalizedName, '.m4a') || str_contains($normalizedName, '.mp4');
        $isMP3  = str_contains($mimeType, 'mpeg') || str_contains($normalizedName, '.mp3');
        $isOgg  = str_contains($mimeType, 'ogg') || str_contains($normalizedName, '.ogg');

        $isAudio = str_starts_with($mimeType, 'audio/');
        $isLargeAudio = $isAudio || $isWebM;

        if ($isLargeAudio) {
            Log::info('Archivo de audio aceptado', [
                'tipo' => $mimeType,
                'nombre' => $fileName,
                'es_mp4' => $isMP4,
                'es_mp3' => $isMP3,
                'es_ogg' => $isOgg,
                'es_webm' => $isWebM,
                'tamaño' => $file->getSize()
            ]);
            set_time_limit(7200); // 2 horas para archivos grandes
        } else {
            set_time_limit(600); // 10 minutos para otros formatos
        }

        $filePath = $file->getRealPath();

        $language = $this->resolveLanguage($request->input('language'));
        $apiKey   = config('services.assemblyai.api_key');

        // Log información del archivo
        Log::info('Processing audio file', [
            'original_name' => $fileName,
            'size' => $file->getSize(),
            'mime_type' => $mimeType,
            'language' => $language,
            'is_webm' => $isWebM,
            'is_mp3' => $isMP3,
            'is_ogg' => $isOgg,
        ]);

        // Log específico para archivos grandes
        if ($isLargeAudio) {
            Log::info('Archivo de audio grande detectado - aplicando optimizaciones para archivos largos', [
                'file_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                'original_name' => $fileName,
                'timeout_seconds' => 7200,
                'formato' => $isMP3 ? 'MP3' : ($isMP4 ? 'MP4' : 'Otro'),
                'expected_processing_time' => '1+ hours for long files',
            ]);
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

        Log::info('Audio file loaded', ['size_bytes' => strlen($audioData)]);

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
            $transcriptionConfig = $this->getOptimizedConfigForFormat($mimeType, $baseConfig);

            $transcription = $transcriptionHttp->post('https://api.assemblyai.com/v2/transcript', $transcriptionConfig);

            Log::info('Transcription request sent', [
                'status' => $transcription->status(),
                'successful' => $transcription->successful(),
                'timeout_used' => $timeout,
                'is_webm' => $isWebM,
                'is_ogg' => $isOgg,
                'config_profile' => 'neutral-audio',
                'language_code' => $transcriptionConfig['language_code'] ?? null,
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
            $responseData = $transcription->json();
            $responseText = $transcription->body();
            $statusCode = $transcription->status();

            Log::error('Transcription request failed', [
                'status' => $statusCode,
                'response_json' => $responseData,
                'response_text' => $responseText,
                'config_sent' => $transcriptionConfig,
                'api_key_configured' => !empty(config('services.assemblyai.api_key')),
            ]);

            $failedPath = $this->saveFailedAudio($filePath);

            // Proporcionar mensajes más específicos según el error
            $errorMessage = 'Error en el servicio de transcripción';

            // Verificar si es un problema de saldo
            if (isset($responseData['error']) && str_contains($responseData['error'], 'account balance is negative')) {
                $errorMessage = 'La cuenta de AssemblyAI no tiene créditos suficientes. Contacta al administrador para recargar la cuenta.';
            } elseif ($statusCode === 401) {
                $errorMessage = 'API key de AssemblyAI no válida o expirada';
            } elseif ($statusCode === 400) {
                $errorMessage = 'Formato de archivo de audio no compatible o datos inválidos';
            } elseif ($statusCode === 429) {
                $errorMessage = 'Límite de solicitudes excedido en AssemblyAI';
            } elseif ($statusCode >= 500) {
                $errorMessage = 'Error interno del servicio AssemblyAI';
            }

            return response()->json([
                'error'        => $errorMessage,
                'details'      => $responseData ?: $responseText,
                'failed_audio' => $failedPath,
                'status_code'  => $statusCode,
            ], 500);
        }

        $transcriptId = $transcription->json('id');
        Log::info('Transcription started successfully', ['transcript_id' => $transcriptId]);

        // Guardar temporalmente el audio para identificación de speakers
        $tempAudioPath = null;
        try {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'ogg';
            $tempFilename = "temp-transcription/{$transcriptId}.{$extension}";
            Storage::put($tempFilename, file_get_contents($filePath));
            $tempAudioPath = $tempFilename;
            Log::info('Audio saved temporarily for speaker identification', [
                'transcript_id' => $transcriptId,
                'temp_path' => $tempAudioPath
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save temporary audio', [
                'transcript_id' => $transcriptId,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'id' => $transcriptId,
            'temp_audio_path' => $tempAudioPath
        ]);
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

    private function guessExtensionFromAudio(string $transcriptId): string
    {
        // Intentar encontrar el archivo temporal con diferentes extensiones
        $extensions = ['ogg', 'webm', 'mp3', 'wav', 'm4a', 'mp4'];
        foreach ($extensions as $ext) {
            if (Storage::exists("temp-transcription/{$transcriptId}.{$ext}")) {
                return $ext;
            }
        }
        return 'ogg'; // Default
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

            $transcriptionData = $response->json();
            
            // Si la transcripción está completada, agregar temp_audio_path si existe
            if ($transcriptionData['status'] === 'completed') {
                $tempAudioPath = "temp-transcription/{$id}." . $this->guessExtensionFromAudio($id);
                if (Storage::exists($tempAudioPath)) {
                    $transcriptionData['temp_audio_path'] = $tempAudioPath;
                }
            }

            return $transcriptionData;

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
        $language = $this->resolveLanguage($request->input('language'));
        $filename = $request->input('filename');
        $lower = strtolower($filename);
        $ext = pathinfo($lower, PATHINFO_EXTENSION);
        Log::info('Chunked upload accepted (format agnostic)', [
            'filename' => $filename,
            'extension' => $ext,
            'size_mb' => round($request->input('size') / 1024 / 1024, 2),
            'chunks' => $request->input('chunks'),
            'language' => $language,
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
            'total_size' => $request->input('size'),
            'language' => $language,
            'chunks_expected' => $chunks,
            'chunks_received' => 0,
            'created_at' => now()->toISOString()
        ];

        file_put_contents(
            storage_path("app/temp-uploads/{$uploadId}/metadata.json"),
            json_encode($metadata)
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

        $validator = Validator::make($request->all(), [
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'upload_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid chunk upload payload',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $chunk = $request->file('chunk');
        if (!$chunk || !$chunk->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk file is missing or invalid'
            ], 422);
        }

        $uploadId = $data['upload_id'];
        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];

        $uploadDir = storage_path("app/temp-uploads/{$uploadId}");
        $metadataPath = "{$uploadDir}/metadata.json";

        if (!file_exists($metadataPath)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        $metadataContent = file_get_contents($metadataPath);
        $metadata = json_decode($metadataContent, true);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        // Obtener el tamaño ANTES de mover el archivo
        $chunkSize = $chunk->getSize() ?? 0;

        // Guardar el chunk (sobre-escribe si se reintenta el mismo índice)
        $chunk->move($uploadDir, "chunk_{$chunkIndex}");

        // Actualizar metadatos de forma atómica para evitar condiciones de carrera
        $this->safelyUpdateChunkMetadata($metadataPath, function (&$metadata) use ($chunkIndex, $totalChunks) {
            if (!is_array($metadata)) {
                $metadata = [];
            }
            if (!isset($metadata['chunks_expected']) || !is_int($metadata['chunks_expected'])) {
                $metadata['chunks_expected'] = $totalChunks;
            }
            if (!isset($metadata['received_indices']) || !is_array($metadata['received_indices'])) {
                $metadata['received_indices'] = [];
            }
            if (!in_array($chunkIndex, $metadata['received_indices'])) {
                $metadata['received_indices'][] = $chunkIndex;
            }
            // Normalizar: chunks_received = número único de índices recibidos
            $metadata['chunks_received'] = count($metadata['received_indices']);
        });

        // Leer nuevamente para logging
        $metadata = json_decode(file_get_contents($metadataPath), true);

        Log::info('Chunk uploaded', [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'chunk_size' => $chunkSize,
            'chunks_received' => $metadata['chunks_received'] ?? null,
            'chunks_expected' => $metadata['chunks_expected'] ?? $totalChunks,
            'received_indices' => $metadata['received_indices'] ?? []
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

        // Recontar realmente los archivos presentes para robustez
        $files = glob("{$uploadDir}/chunk_*");
        $presentCount = is_array($files) ? count($files) : 0;
        $expected = (int) ($metadata['chunks_expected'] ?? 0);

        // Calcular índices presentes y faltantes
        $presentIndices = [];
        foreach ($files as $f) {
            $base = basename($f);
            $parts = explode('_', $base);
            $idx = (int) end($parts);
            $presentIndices[] = $idx;
        }
        sort($presentIndices);
        $missingIndices = [];
        for ($i = 0; $i < $expected; $i++) {
            if (!in_array($i, $presentIndices, true)) {
                $missingIndices[] = $i;
            }
        }

        // Si faltan realmente archivos, devolver detalle para reintento selectivo
        if (!empty($missingIndices)) {
            Log::warning('Finalize chunked upload - missing chunk files', [
                'upload_id' => $uploadId,
                'expected' => $expected,
                'present_count' => $presentCount,
                'missing_indices' => $missingIndices,
                'reported_chunks_received' => $metadata['chunks_received'] ?? null,
            ]);
            return response()->json([
                'error' => 'missing_chunks',
                'message' => 'Algunos fragmentos no fueron recibidos correctamente',
                'expected' => $expected,
                'present_count' => $presentCount,
                'missing_indices' => $missingIndices,
            ], 400);
        }

        // Si todos los archivos están presentes pero metadata quedó desincronizado (por condición de carrera) lo corregimos
        if (($metadata['chunks_received'] ?? 0) !== $expected) {
            $this->safelyUpdateChunkMetadata($metadataPath, function (&$metadataInner) use ($expected, $presentIndices) {
                $metadataInner['chunks_received'] = $expected;
                $metadataInner['received_indices'] = $presentIndices;
            });
            $metadata = json_decode(file_get_contents($metadataPath), true);
            Log::info('Finalize chunked upload - metadata reconciled', [
                'upload_id' => $uploadId,
                'expected' => $expected,
                'reconciled_chunks_received' => $metadata['chunks_received']
            ]);
        }

        $trackingId = (string) Str::uuid();
        
        // Calcular tamaño total del archivo para decidir estrategia de procesamiento
        $totalSize = $metadata['total_size'] ?? 0;
        $maxSyncSize = config('audio.max_file_size', 52428800); // 50MB
        
        Cache::put("chunked_transcription:{$trackingId}", [
            'status' => 'queued',
            'total_size' => $totalSize,
            'created_at' => now()->toISOString()
        ]);

        // Decidir modo de procesamiento basado en tamaño y configuración
        $processingMode = strtolower(config('audio.chunked_processing_mode', 'async'));
        $queueEnabled = config('audio.queue_enabled', true);
        
        // Forzar cola para archivos grandes independientemente de la configuración
        if ($totalSize > $maxSyncSize && $queueEnabled) {
            $processingMode = 'queue';
        }

        if ($processingMode === 'queue' && $queueEnabled) {
            Log::info('Queuing chunked transcription for background processing', [
                'upload_id' => $uploadId,
                'tracking_id' => $trackingId,
                'total_size' => $totalSize,
                'chunks' => $expected
            ]);
            
            ProcessChunkedTranscription::dispatch($uploadId, $trackingId)
                ->onQueue(config('audio.queue_name', 'audio-processing'));
        } else {
            Log::info('Processing chunked transcription synchronously', [
                'upload_id' => $uploadId,
                'tracking_id' => $trackingId,
                'mode' => $processingMode,
                'total_size' => $totalSize,
                'chunks' => $expected
            ]);

            // Aumentar límites para procesamiento síncrono
            ini_set('memory_limit', config('audio.process_memory_limit', '512M'));
            set_time_limit(config('audio.conversion_timeout', 600));

            ProcessChunkedTranscription::dispatchSync($uploadId, $trackingId);
        }

        return response()->json([
            'tracking_id' => $trackingId,
        ], 202);
    }

    /**
     * Actualiza metadata.json con bloqueo para evitar condiciones de carrera entre subidas concurrentes.
     */
    private function safelyUpdateChunkMetadata(string $metadataPath, callable $updater): void
    {
        $dir = dirname($metadataPath);
        if (!is_dir($dir)) {
            return; // sesión inválida
        }
        $fp = fopen($metadataPath, 'c+');
        if (!$fp) {
            return; // no se pudo abrir
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                return;
            }
            // Leer contenido actual
            $raw = stream_get_contents($fp);
            if ($raw === false || $raw === '') {
                $metadata = [];
            } else {
                $metadata = json_decode($raw, true);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
            }

            // Aplicar actualización
            $updater($metadata);

            // Rewind & truncate & write
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($metadata));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    private function processLargeAudioFile($filePath, $language, $mimeType = '')
    {
        $apiKey = config('services.assemblyai.api_key');

        if (empty($apiKey)) {
            throw new \Exception('AssemblyAI API key missing');
        }

        $lowerPath = strtolower($filePath);
        $mimeType = strtolower((string) $mimeType);
        $isWebM = str_contains($mimeType, 'webm') || str_contains($lowerPath, '.webm');
        $isMP4 = str_contains($mimeType, 'mp4') || str_contains($lowerPath, '.m4a') || str_contains($lowerPath, '.mp4');
        $isMP3 = str_contains($mimeType, 'mpeg') || str_contains($lowerPath, '.mp3');
        $isOgg = str_contains($mimeType, 'ogg') || str_contains($lowerPath, '.ogg');
        $isAudio = str_starts_with($mimeType, 'audio/');
        $isLargeAudio = $isAudio || $isWebM;

        Log::info('Processing large audio file', [
            'file_path' => basename($filePath),
            'mime_type' => $mimeType,
            'is_mp4' => $isMP4,
            'is_mp3' => $isMP3,
            'is_ogg' => $isOgg,
            'is_webm' => $isWebM,
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
            'speech_threshold'        => 0.4,        // Balanceado para evitar falsos positivos
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
        $payload = $this->getOptimizedConfigForFormat($mimeType, $basePayload);

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

            $response = Http::withHeaders([
                'authorization' => config('app.assemblyai_api_key'),
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
                
                // Intentar identificar hablantes automáticamente
                try {
                    $userIds = [];
                    $currentUser = auth()->user();
                    
                    // 1. Si hay una reunión con miembros, usar esos usuarios
                    $recording = \App\Models\Recording::where('transcription_id', $id)->first();
                    if ($recording && $recording->reunion) {
                        $userIds = $recording->reunion->members()->pluck('id')->toArray();
                    }
                    
                    // 2. Si no hay miembros de reunión, usar contactos del usuario actual
                    if (empty($userIds) && $currentUser) {
                        // Incluir al usuario actual si tiene voice_embedding
                        if ($currentUser->voice_embedding) {
                            $userIds[] = $currentUser->id;
                        }
                        
                        // Agregar contactos con voice_embedding
                        $contactIds = \App\Models\Contact::where('user_id', $currentUser->id)
                            ->pluck('contact_id')
                            ->toArray();
                        
                        if (!empty($contactIds)) {
                            $contactsWithVoice = \App\Models\User::whereIn('id', $contactIds)
                                ->whereNotNull('voice_embedding')
                                ->pluck('id')
                                ->toArray();
                            $userIds = array_merge($userIds, $contactsWithVoice);
                        }
                        
                        $userIds = array_unique($userIds);
                    }
                    
                    // 3. Intentar identificar si hay usuarios con perfil de voz
                    if (!empty($userIds) && $recording && $recording->ruta_archivo) {
                        $data['utterances'] = $this->identifySpeakersAutomatically(
                            $data['utterances'],
                            $recording->ruta_archivo,
                            $userIds
                        );
                        Log::info('Automatic speaker identification attempted', [
                            'transcription_id' => $id,
                            'users_checked' => count($userIds),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not perform automatic speaker identification', [
                        'transcription_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }

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

    /**
     * Identifica automáticamente a los hablantes usando perfiles de voz
     */
    private function identifySpeakersAutomatically(array $utterances, string $audioPath, array $userIds): array
    {
        try {
            $threshold = (float) config('audio.speaker_match_threshold', 0.75);

            // Obtener embeddings de usuarios con perfil de voz
            $users = \App\Models\User::whereIn('id', $userIds)
                ->whereNotNull('voice_embedding')
                ->get();
            
            if ($users->isEmpty()) {
                Log::info('No users with voice profiles found');
                return $utterances;
            }

            $userEmbeddings = [];
            foreach ($users as $user) {
                // Aceptar embeddings de 76 o más dimensiones (normalizar a 76 para consistencia)
                if (is_array($user->voice_embedding) && count($user->voice_embedding) >= 76) {
                    $embedding = array_values($user->voice_embedding);
                    if (count($embedding) > 76) {
                        $embedding = array_slice($embedding, 0, 76);
                    }
                    $userEmbeddings[$user->id] = [
                        'embedding' => $embedding,
                        'name' => $this->resolveVoiceDisplayName($user),
                    ];
                }
            }

            if (empty($userEmbeddings)) {
                Log::info('No valid voice embeddings found', [
                    'users_checked' => $users->count(),
                    'user_ids' => $userIds
                ]);
                return $utterances;
            }

            Log::info('Valid voice embeddings found', [
                'count' => count($userEmbeddings),
                'user_ids' => array_keys($userEmbeddings)
            ]);

            // Agrupar utterances por speaker para procesar muestras representativas
            $speakerGroups = [];
            foreach ($utterances as $utterance) {
                $speaker = $utterance['speaker'] ?? 'Unknown';
                if (!isset($speakerGroups[$speaker])) {
                    $speakerGroups[$speaker] = [];
                }
                $speakerGroups[$speaker][] = $utterance;
            }

            // Para cada speaker, tomar una muestra y compararla con los perfiles
            $speakerMatches = [];
            $pythonPath = config('audio.python_bin', env('PYTHON_BIN', 'python'));
            $scriptPath = base_path('tools/identify_speakers.py');

            foreach ($speakerGroups as $speaker => $group) {
                // Tomar hasta 3 muestras representativas (inicio, medio, fin)
                $samples = $this->selectRepresentativeSamples($group, 3);
                $totalSamples = count($samples);
                
                $segments = array_map(function($utterance) use ($speaker) {
                    return [
                        'start' => $utterance['start'] / 1000, // Convertir a segundos
                        'end' => $utterance['end'] / 1000,
                        'speaker' => $speaker,
                    ];
                }, $samples);

                $embeddings = [];
                foreach ($userEmbeddings as $userId => $data) {
                    $embeddings[$userId] = $data['embedding'];
                }

                $input = json_encode([
                    'segments' => $segments,
                    'user_embeddings' => $embeddings,
                ]);

                $process = new \Symfony\Component\Process\Process([
                    $pythonPath,
                    $scriptPath,
                    storage_path('app/' . $audioPath),
                    $input,
                ]);
                
                $process->setTimeout(60);
                $process->setEnv([
                    'FFMPEG_BIN' => config('audio.ffmpeg_bin', env('FFMPEG_BIN', 'ffmpeg')),
                    'PYTHONIOENCODING' => 'utf-8',
                    'LIBROSA_CACHE_DIR' => '',
                    'LIBROSA_CACHE_LEVEL' => '0',
                    'JOBLIB_START_METHOD' => 'loky',
                ]);
                
                $process->run();

                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $errorOutput = $process->getErrorOutput();
                    
                    if ($errorOutput) {
                        Log::info('Python script debug output', [
                            'speaker' => $speaker,
                            'stderr' => $errorOutput
                        ]);
                    }
                    
                    $results = json_decode(trim($output), true);
                    
                    if (is_array($results)) {
                        // Determinar el mejor match para este speaker
                        $userVotes = [];
                        foreach ($results as $result) {
                            if ($result['matched_user_id'] && $result['confidence'] >= $threshold) {
                                $userId = $result['matched_user_id'];
                                if (!isset($userVotes[$userId])) {
                                    $userVotes[$userId] = ['count' => 0, 'total_confidence' => 0];
                                }
                                $userVotes[$userId]['count']++;
                                $userVotes[$userId]['total_confidence'] += $result['confidence'];
                            }
                        }

                        if (!empty($userVotes)) {
                            // Encontrar el usuario con más votos y mejor confianza promedio
                            $bestUserId = null;
                            $bestScore = 0;
                            foreach ($userVotes as $userId => $votes) {
                                $avgConfidence = $votes['total_confidence'] / $votes['count'];
                                $score = $votes['count'] * $avgConfidence;
                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $bestUserId = $userId;
                                }
                            }

                            if ($bestUserId) {
                                $minVotes = $totalSamples > 1 ? max(2, (int) ceil($totalSamples * 0.6)) : 1;
                                $matchedCount = $userVotes[$bestUserId]['count'];
                                $avgConfidence = $userVotes[$bestUserId]['total_confidence'] / $matchedCount;

                                if ($matchedCount < $minVotes) {
                                    Log::info('Speaker match discarded due to low vote count', [
                                        'speaker' => $speaker,
                                        'matched_user_id' => $bestUserId,
                                        'matched_count' => $matchedCount,
                                        'required_votes' => $minVotes,
                                        'total_samples' => $totalSamples,
                                    ]);
                                    continue;
                                }

                                $speakerMatches[$speaker] = [
                                    'user_id' => $bestUserId,
                                    'name' => $userEmbeddings[$bestUserId]['name'],
                                    'confidence' => $avgConfidence,
                                ];

                                Log::info('Speaker matched', [
                                    'speaker' => $speaker,
                                    'user_id' => $bestUserId,
                                    'name' => $userEmbeddings[$bestUserId]['name'],
                                    'confidence' => $speakerMatches[$speaker]['confidence'],
                                ]);
                            }
                        }
                    }
                } else {
                    Log::warning('Python speaker identification failed', [
                        'speaker' => $speaker,
                        'exit_code' => $process->getExitCode(),
                        'stderr' => $process->getErrorOutput(),
                        'stdout' => $process->getOutput()
                    ]);
                }
            }

            // Evitar asignar el mismo usuario a varios speakers (mantener el mejor)
            $bestSpeakerPerUser = [];
            foreach ($speakerMatches as $speaker => $match) {
                $userId = $match['user_id'];
                if (!isset($bestSpeakerPerUser[$userId]) || $match['confidence'] > $bestSpeakerPerUser[$userId]['confidence']) {
                    $bestSpeakerPerUser[$userId] = [
                        'speaker' => $speaker,
                        'confidence' => $match['confidence'],
                    ];
                }
            }

            foreach ($speakerMatches as $speaker => $match) {
                $userId = $match['user_id'];
                if ($bestSpeakerPerUser[$userId]['speaker'] !== $speaker) {
                    unset($speakerMatches[$speaker]);
                }
            }

            // Asignar nombres a los utterances
            foreach ($utterances as &$utterance) {
                $speaker = $utterance['speaker'] ?? 'Unknown';
                if (isset($speakerMatches[$speaker])) {
                    $utterance['speaker_name'] = $speakerMatches[$speaker]['name'];
                    $utterance['speaker_user_id'] = $speakerMatches[$speaker]['user_id'];
                    $utterance['speaker_confidence'] = $speakerMatches[$speaker]['confidence'];
                    $utterance['auto_identified'] = true;
                } else {
                    $utterance['speaker_name'] = $this->formatFallbackSpeakerName($speaker);
                    $utterance['auto_identified'] = false;
                }
            }

            return $utterances;

        } catch (\Exception $e) {
            Log::error('Error in automatic speaker identification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $utterances;
        }
    }

    /**
     * Selecciona muestras representativas de un grupo de utterances
     */
    private function selectRepresentativeSamples(array $utterances, int $count): array
    {
        if (count($utterances) <= $count) {
            return $utterances;
        }

        $samples = [];
        $indices = [
            0, // Primera
            intval(count($utterances) / 2), // Medio
            count($utterances) - 1, // Última
        ];

        foreach ($indices as $index) {
            if (isset($utterances[$index])) {
                $samples[] = $utterances[$index];
            }
        }

        return array_slice($samples, 0, $count);
    }

    private function formatFallbackSpeakerName($speaker): string
    {
        if (is_numeric($speaker)) {
            $index = (int) $speaker;
            $alphabetIndex = max(0, $index);
            $letter = chr(ord('A') + ($alphabetIndex % 26));
            return "Hablante {$letter}";
        }

        if (is_string($speaker) && strlen($speaker) === 1) {
            return "Hablante " . strtoupper($speaker);
        }

        $label = trim((string) $speaker);
        if ($label === '') {
            return 'Hablante';
        }

        return "Hablante {$label}";
    }

    private function resolveVoiceDisplayName(\App\Models\User $user): string
    {
        return $user->full_name
            ?? $user->name
            ?? $user->username
            ?? $user->email
            ?? 'Usuario';
    }

    /**
     * Identifica speakers en una transcripción antes de guardar
     * Endpoint para llamar desde el frontend con audio local y transcripción
     */
    public function identifySpeakersBeforeSave(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'audio_path' => 'required|string',
                'utterances' => 'required|array',
                'reunion_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 400);
            }

            $audioPath = $request->input('audio_path');
            $utterances = $request->input('utterances');
            $reunionId = $request->input('reunion_id');

            Log::info('identifySpeakersBeforeSave called', [
                'audio_path' => $audioPath,
                'utterances_count' => count($utterances),
                'reunion_id' => $reunionId,
                'user_id' => auth()->id()
            ]);

            // Verificar que el archivo existe
            $fullPath = storage_path('app/' . $audioPath);
            if (!file_exists($fullPath)) {
                Log::error('Audio file not found for speaker identification', [
                    'audio_path' => $audioPath,
                    'full_path' => $fullPath,
                    'storage_path' => storage_path('app/')
                ]);
                return response()->json([
                    'error' => 'Audio file not found',
                    'path' => $audioPath,
                    'full_path' => $fullPath
                ], 404);
            }

            Log::info('Audio file found', [
                'full_path' => $fullPath,
                'size' => filesize($fullPath)
            ]);

            // Obtener IDs de usuarios para identificación:
            // Solo buscar en contactos del usuario actual + el usuario mismo
            $currentUser = auth()->user();
            $userIds = [];
            
            if ($currentUser) {
                // Incluir al usuario actual
                $userIds[] = $currentUser->id;
                
                // Agregar solo los contactos del usuario que tengan voice_embedding
                $contactIds = \App\Models\Contact::where('user_id', $currentUser->id)
                    ->pluck('contact_id')
                    ->toArray();
                
                if (!empty($contactIds)) {
                    $contactsWithVoice = \App\Models\User::whereIn('id', $contactIds)
                        ->whereNotNull('voice_embedding')
                        ->pluck('id')
                        ->toArray();
                    $userIds = array_merge($userIds, $contactsWithVoice);
                }
                
                // Si el usuario actual tiene voice_embedding, mantenerlo; si no, solo buscar en contactos
                if (empty($currentUser->voice_embedding)) {
                    $userIds = array_filter($userIds, fn($id) => $id !== $currentUser->id);
                }
                
                $userIds = array_unique($userIds);
            }

            Log::info('Users for speaker identification (contacts only)', [
                'user_ids' => $userIds,
                'count' => count($userIds),
                'current_user' => $currentUser?->id
            ]);

            if (empty($userIds)) {
                Log::info('No contacts with voice profiles found - keeping default speaker names');
                
                // Mantener los nombres de hablante por defecto (Hablante A, Hablante B, etc.)
                foreach ($utterances as &$utterance) {
                    $speaker = $utterance['speaker'] ?? 'Unknown';
                    $utterance['speaker_name'] = $this->formatFallbackSpeakerName($speaker);
                    $utterance['auto_identified'] = false;
                }
                
                return response()->json([
                    'utterances' => $utterances,
                    'identified' => false,
                    'message' => 'No hay contactos con perfil de voz grabado'
                ]);
            }

            // Ejecutar identificación
            Log::info('Calling identifySpeakersAutomatically');
            $identifiedUtterances = $this->identifySpeakersAutomatically(
                $utterances,
                $audioPath,
                $userIds
            );

            Log::info('Speaker identification completed', [
                'utterances_count' => count($identifiedUtterances),
                'sample_speaker' => $identifiedUtterances[0]['speaker'] ?? null,
                'sample_speaker_name' => $identifiedUtterances[0]['speaker_name'] ?? null,
                'sample_auto_identified' => $identifiedUtterances[0]['auto_identified'] ?? null
            ]);

            return response()->json([
                'utterances' => $identifiedUtterances,
                'identified' => true,
                'user_count' => count($userIds)
            ]);

        } catch (\Exception $e) {
            Log::error('Error identifying speakers before save', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to identify speakers',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
