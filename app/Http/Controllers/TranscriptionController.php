<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TranscriptionController extends Controller
{
    public function store(Request $request)
    {
        // Aumentar el tiempo límite para archivos grandes
        set_time_limit(600); // 10 minutos

        $request->validate([
            'audio'    => 'required|file|max:102400', // Máximo 100MB
            'language' => 'nullable|in:es,en,fr,de',
        ]);

        $file = $request->file('audio');
        $filePath = $file->getRealPath();
        $language = $request->input('language', 'es');
        $apiKey   = config('services.assemblyai.api_key');

        // Log información del archivo
        Log::info('Processing audio file', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'language' => $language,
        ]);

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
            $timeout = config('services.assemblyai.timeout', 300);
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
            $timeout = config('services.assemblyai.timeout', 300);
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

            $transcription = $transcriptionHttp->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url'      => $uploadUrl,
                'speaker_labels' => true,
                'punctuate'      => true,
                'format_text'    => true,
                'language_code'  => $language,
            ]);

            Log::info('Transcription request sent', [
                'status' => $transcription->status(),
                'successful' => $transcription->successful(),
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
        $apiKey = config('services.assemblyai.api_key');

        $response = Http::withHeaders([
            'authorization' => $apiKey,
        ])
            ->get("https://api.assemblyai.com/v2/transcript/{$id}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Status check failed', 'details' => $response->json()], 500);
        }

        return $response->json();
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

        // Actualizar metadatos
        $metadata = json_decode(file_get_contents($metadataPath), true);
        $metadata['chunks_received']++;

        file_put_contents($metadataPath, json_encode($metadata));

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

        try {
            // Combinar todos los chunks en un solo archivo
            $finalFilePath = "{$uploadDir}/final_audio";
            $finalFile = fopen($finalFilePath, 'wb');

            for ($i = 0; $i < $metadata['chunks_expected']; $i++) {
                $chunkPath = "{$uploadDir}/chunk_{$i}";
                if (!file_exists($chunkPath)) {
                    fclose($finalFile);
                    throw new \Exception("Missing chunk {$i}");
                }

                $chunkData = file_get_contents($chunkPath);
                fwrite($finalFile, $chunkData);
            }

            fclose($finalFile);

            Log::info('Chunks combined successfully', [
                'upload_id' => $uploadId,
                'final_size' => filesize($finalFilePath),
                'expected_size' => $metadata['total_size']
            ]);

            // Procesar con AssemblyAI
            $transcriptionId = $this->processLargeAudioFile($finalFilePath, $metadata['language']);

            // Limpiar archivos temporales
            $this->cleanupTempFiles($uploadDir);

            return response()->json(['id' => $transcriptionId]);

        } catch (\Throwable $e) {
            Log::error('Failed to finalize chunked upload', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage()
            ]);

            // Limpiar archivos temporales en caso de error
            $this->cleanupTempFiles($uploadDir);

            $failedPath = $this->saveFailedAudio($finalFilePath ?? null);
            return response()->json([
                'error' => 'Failed to process combined audio',
                'details' => $e->getMessage(),
                'failed_audio' => $failedPath
            ], 500);
        }
    }

    private function processLargeAudioFile($filePath, $language)
    {
        $apiKey = config('services.assemblyai.api_key');

        if (empty($apiKey)) {
            throw new \Exception('AssemblyAI API key missing');
        }

        // Configuración optimizada para archivos grandes
        $timeout = (int) config('services.assemblyai.timeout', 300);
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
        $transcriptResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json'
        ])
        ->timeout(60) // Timeout más corto para la creación de transcripción
        ->post('https://api.assemblyai.com/v2/transcript', [
            'audio_url' => $audioUrl,
            'language_code' => $language,
            'speaker_labels' => true,
            'auto_chapters' => true,
            'summarization' => true,
            'summary_model' => 'informative',
            'summary_type' => 'bullets'
        ]);

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
}
