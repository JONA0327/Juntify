<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Exceptions\FfmpegUnavailableException;
use App\Services\AudioConversionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessChunkedTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $uploadId;
    protected string $trackingId;

    public function __construct(string $uploadId, string $trackingId)
    {
        $this->uploadId = $uploadId;
        $this->trackingId = $trackingId;
    }

    public function handle(): void
    {
        $cacheKey = "chunked_transcription:{$this->trackingId}";
        Cache::put($cacheKey, ['status' => 'processing']);

        $uploadDir = storage_path("app/temp-uploads/{$this->uploadId}");
        $metadataPath = "{$uploadDir}/metadata.json";

        $finalFilePath = null;
        $processedPath = null;

        try {
            $metadata = json_decode(file_get_contents($metadataPath), true);
            if (!is_array($metadata)) {
                throw new RuntimeException('Upload metadata is missing or invalid');
            }

            $filename = $metadata['filename'] ?? '';
            $originalExtension = strtolower($metadata['original_extension'] ?? pathinfo($filename, PATHINFO_EXTENSION));
            $originalMimeType = $metadata['original_mime_type'] ?? $this->guessMimeType($originalExtension);
            $isWebM = $originalExtension === 'webm';

            $extensionSuffix = $originalExtension ? ".{$originalExtension}" : '';
            $finalFilePath = "{$uploadDir}/final_audio{$extensionSuffix}";

            if ($isWebM) {
                Log::info('WebM file detected in chunked processing', [
                    'filename' => $filename,
                    'chunks' => $metadata['chunks_expected'],
                ]);
            }

            // Combinar chunks utilizando la estrategia adecuada
            if ($isWebM) {
                $this->combineWebMChunks($uploadDir, $metadata, $finalFilePath);
            } else {
                $this->combineChunksStandard($uploadDir, $metadata, $finalFilePath);
            }

            Log::info('Chunks combined successfully', [
                'upload_id' => $this->uploadId,
                'tracking_id' => $this->trackingId,
                'final_size' => filesize($finalFilePath),
                'expected_size' => $metadata['total_size'] ?? null,
                'is_webm' => $isWebM,
                'chunks_expected' => $metadata['chunks_expected'],
                'chunks_received' => $metadata['chunks_received'],
                'file_path' => $finalFilePath,
            ]);

            // Para archivos WebM, verificar integridad adicional
            if ($isWebM) {
                $actualSize = filesize($finalFilePath);
                $expectedSize = $metadata['total_size'] ?? 0;

                if ($actualSize !== $expectedSize) {
                    Log::warning('WebM file size mismatch detected', [
                        'expected_size' => $expectedSize,
                        'actual_size' => $actualSize,
                        'difference' => $actualSize - $expectedSize,
                    ]);
                }

                Log::info('WebM file integrity check', [
                    'size_match' => $actualSize === $expectedSize,
                    'file_size_mb' => round($actualSize / 1024 / 1024, 2),
                ]);
            }

            $processedPath = $finalFilePath;

            /** @var AudioConversionService $audioConversionService */
            $audioConversionService = app(AudioConversionService::class);

            try {
                $conversionResult = $audioConversionService->convertToMp3($finalFilePath, $originalMimeType, $originalExtension);
                $processedPath = $conversionResult['path'];

                Log::info('Combined audio converted to MP3', [
                    'upload_id' => $this->uploadId,
                    'tracking_id' => $this->trackingId,
                    'source_extension' => $originalExtension,
                    'was_converted' => $conversionResult['was_converted'] ?? null,
                    'mp3_path' => $processedPath,
                ]);
            } catch (FfmpegUnavailableException $ffmpegUnavailableException) {
                $supportedExtensions = $metadata['supported_extensions'] ?? [];
                if (empty($supportedExtensions) && isset($metadata['accepted_formats']) && is_array($metadata['accepted_formats'])) {
                    $supportedExtensions = array_keys($metadata['accepted_formats']);
                }
                $supportedExtensions = array_map('strtolower', $supportedExtensions);
                $isSupported = empty($supportedExtensions) || in_array($originalExtension, $supportedExtensions, true);

                if ($isSupported) {
                    $processedPath = $finalFilePath;

                    Log::warning('FFmpeg unavailable during chunked processing, falling back to original audio upload', [
                        'upload_id' => $this->uploadId,
                        'tracking_id' => $this->trackingId,
                        'source_extension' => $originalExtension,
                    ]);
                } else {
                    $message = 'No se pudo convertir el audio combinado a MP3';
                    Cache::put($cacheKey, [
                        'status' => 'error',
                        'error' => $message,
                    ]);

                    Log::error('FFmpeg unavailable and source format not supported for upload', [
                        'upload_id' => $this->uploadId,
                        'tracking_id' => $this->trackingId,
                        'source_extension' => $originalExtension,
                        'supported_extensions' => $supportedExtensions,
                        'error' => $ffmpegUnavailableException->getMessage(),
                    ]);

                    throw new RuntimeException($message, 0, $ffmpegUnavailableException);
                }
            } catch (Throwable $conversionException) {
                $message = 'No se pudo convertir el audio combinado a MP3';
                Cache::put($cacheKey, [
                    'status' => 'error',
                    'error' => $message,
                ]);

                Log::error('Failed to convert combined audio to MP3', [
                    'upload_id' => $this->uploadId,
                    'tracking_id' => $this->trackingId,
                    'source_extension' => $originalExtension,
                    'error' => $conversionException->getMessage(),
                ]);

                throw new RuntimeException($message, 0, $conversionException);
            }

            $transcriptionId = $this->uploadToAssemblyAI($processedPath, $metadata['language'] ?? 'es');

            Cache::put($cacheKey, [
                'status' => 'processing',
                'transcription_id' => $transcriptionId,
            ]);
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            Log::error('Failed to process chunked transcription', [
                'upload_id' => $this->uploadId,
                'tracking_id' => $this->trackingId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanupTempFiles($uploadDir, $finalFilePath, $processedPath);
        }
    }

    private function uploadToAssemblyAI(string $filePath, string $language): string
    {
        $apiKey = config('services.assemblyai.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('AssemblyAI API key missing');
        }

        $timeout = (int) config('services.assemblyai.timeout', 3600);
        $connectTimeout = (int) config('services.assemblyai.connect_timeout', 60);

        $audioData = file_get_contents($filePath);
        if ($audioData === false) {
            throw new RuntimeException('Unable to read MP3 file for upload');
        }

        $http = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'authorization' => $apiKey,
                'content-type' => 'application/octet-stream',
            ])
            ->withBody($audioData, 'application/octet-stream');

        if (!config('services.assemblyai.verify_ssl', true)) {
            $http = $http->withoutVerifying();
        } else {
            $http = $http->withOptions([
                'verify' => config('services.assemblyai.verify_ssl', true),
            ]);
        }

        $uploadResponse = $http->post('https://api.assemblyai.com/v2/upload');

        if (! $uploadResponse->successful()) {
            throw new RuntimeException('AssemblyAI upload failed: ' . $uploadResponse->body());
        }

        $audioUrl = $uploadResponse->json('upload_url');

        $supportsExtras = $language === 'en';

        $basePayload = [
            'audio_url' => $audioUrl,
            'language_code' => $language,
            'speaker_labels' => true,
            'punctuate' => true,
            'format_text' => false,              // Desactivado para mejor speaker detection
            'speech_threshold' => 0.4,           // Balanceado para evitar falsos positivos
            'speed_boost' => false,              // Sin speed boost para mejor calidad
            'dual_channel' => false,             // Forzar mono-análisis
            // No incluir speakers_expected para permitir detección automática
        ];

        $payload = $basePayload;

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

        $transcriptionHttp = Http::timeout(60)
            ->withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json',
            ]);

        if (!config('services.assemblyai.verify_ssl', true)) {
            $transcriptionHttp = $transcriptionHttp->withoutVerifying();
        } else {
            $transcriptionHttp = $transcriptionHttp->withOptions([
                'verify' => config('services.assemblyai.verify_ssl', true),
            ]);
        }

        $transcriptResponse = $transcriptionHttp->post('https://api.assemblyai.com/v2/transcript', $payload);

        if (! $transcriptResponse->successful()) {
            throw new RuntimeException('AssemblyAI transcript creation failed: ' . $transcriptResponse->body());
        }

        return $transcriptResponse->json('id');
    }

    /**
     * Combina chunks de archivos WebM de manera más cuidadosa
     */
    private function combineWebMChunks(string $uploadDir, array $metadata, string $finalFilePath): void
    {
        Log::info('Combining WebM chunks with enhanced method', [
            'chunks_expected' => $metadata['chunks_expected'],
            'total_size' => $metadata['total_size'],
        ]);

        $finalFile = fopen($finalFilePath, 'wb');
        $totalWritten = 0;
        $chunkSizes = [];

        for ($i = 0; $i < $metadata['chunks_expected']; $i++) {
            $chunkPath = "{$uploadDir}/chunk_{$i}";

            if (!file_exists($chunkPath)) {
                Log::error("WebM chunk missing: chunk_{$i}");
                continue;
            }

            $chunkData = file_get_contents($chunkPath);
            $chunkSize = strlen($chunkData);
            $chunkSizes[] = $chunkSize;

            $written = fwrite($finalFile, $chunkData);
            $totalWritten += $written;

            Log::debug("WebM chunk_{$i} written", [
                'chunk_size' => $chunkSize,
                'written' => $written,
                'total_written' => $totalWritten,
            ]);

            // Verificar integridad de escritura
            if ($written !== $chunkSize) {
                Log::warning("WebM chunk write mismatch", [
                    'chunk' => $i,
                    'expected' => $chunkSize,
                    'written' => $written,
                ]);
            }
        }

        fclose($finalFile);

        // Verificación final para WebM
        $finalSize = filesize($finalFilePath);
        Log::info('WebM chunks combination completed', [
            'chunks_processed' => count($chunkSizes),
            'chunk_sizes' => $chunkSizes,
            'final_size' => $finalSize,
            'expected_size' => $metadata['total_size'],
            'size_match' => $finalSize === $metadata['total_size'],
        ]);
    }

    /**
     * Combina chunks usando el método estándar
     */
    private function combineChunksStandard(string $uploadDir, array $metadata, string $finalFilePath): void
    {
        $finalFile = fopen($finalFilePath, 'wb');

        for ($i = 0; $i < $metadata['chunks_expected']; $i++) {
            $chunkPath = "{$uploadDir}/chunk_{$i}";
            $chunkData = file_get_contents($chunkPath);
            fwrite($finalFile, $chunkData);
        }

        fclose($finalFile);
    }

    private function cleanupTempFiles(string $uploadDir, ?string $finalFilePath = null, ?string $processedPath = null): void
    {
        try {
            if ($finalFilePath && file_exists($finalFilePath)) {
                unlink($finalFilePath);
            }
            if ($processedPath && $processedPath !== $finalFilePath && file_exists($processedPath)) {
                unlink($processedPath);
            }

            if (is_dir($uploadDir)) {
                $files = glob($uploadDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($uploadDir);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to cleanup temp files', [
                'upload_dir' => $uploadDir,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function guessMimeType(?string $extension): ?string
    {
        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'aac' => 'audio/aac',
            'webm' => 'audio/webm',
            default => null,
        };
    }
}

