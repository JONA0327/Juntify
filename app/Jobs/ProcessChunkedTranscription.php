<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

            // Detectar si es archivo WebM
            $filename = $metadata['filename'] ?? '';
            $isWebM = strpos(strtolower($filename), '.webm') !== false;

            if ($isWebM) {
                Log::info('WebM file detected in chunked processing', [
                    'filename' => $filename,
                    'chunks' => $metadata['chunks_expected'],
                ]);
            }

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $extension = $extension ? ".{$extension}" : '';
            $finalFilePath = "{$uploadDir}/final_audio{$extension}";

            // Para archivos WebM, usar una estrategia de combinación más cuidadosa
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

            // Para archivos WebM, intentar validar/comprobar integridad primero (mantiene lógica previa)
            if ($isWebM) {
                $processedPath = $this->processWebMFile($finalFilePath, $metadata);
            }

            // Conversión unificada a OGG (Opus) si está habilitada en config('audio.force_ogg')
            $converted = false;
            if (config('audio.force_ogg')) {
                try {
                    $conversionService = app(\App\Services\AudioConversionService::class);
                    $mimeGuess = @mime_content_type($processedPath) ?: null;
                    $origExt = pathinfo($processedPath, PATHINFO_EXTENSION) ?: null;
                    $result = $conversionService->convertToOgg($processedPath, $mimeGuess, $origExt);
                    if ($result['was_converted']) {
                        $processedPath = $result['path'];
                        $converted = true;
                        Log::info('Chunked transcription audio converted to ogg', [
                            'upload_id' => $this->uploadId,
                            'tracking_id' => $this->trackingId,
                            'original_ext' => $origExt,
                            'mime' => $result['mime_type']
                        ]);
                    }
                } catch (\App\Exceptions\FfmpegUnavailableException $e) {
                    Log::warning('FFmpeg unavailable for chunked transcription conversion', [
                        'upload_id' => $this->uploadId,
                        'tracking_id' => $this->trackingId,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('OGG conversion failed in chunked transcription, proceeding with original', [
                        'upload_id' => $this->uploadId,
                        'tracking_id' => $this->trackingId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $transcriptionId = $this->uploadToAssemblyAI($processedPath, $metadata['language'], $isWebM);

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

    private function uploadToAssemblyAI(string $filePath, string $language, bool $isWebM = false): string
    {
        $apiKey = config('services.assemblyai.api_key');
        if (empty($apiKey)) {
            throw new \Exception('AssemblyAI API key missing');
        }

        // Ajustar timeouts para archivos WebM
        $timeout = $isWebM ?
            (int) config('services.assemblyai.timeout', 3600) : // 1 hora para WebM
            (int) config('services.assemblyai.timeout', 300);  // 5 minutos para otros
        $connectTimeout = (int) config('services.assemblyai.connect_timeout', 60);

        $audioData = file_get_contents($filePath);

        $uploadResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/octet-stream',
        ])
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withOptions([
                'verify' => false,
                'stream' => true,
            ])
            ->withBody($audioData)
            ->post('https://api.assemblyai.com/v2/upload');

        if (! $uploadResponse->successful()) {
            throw new \Exception('AssemblyAI upload failed: ' . $uploadResponse->body());
        }

        $audioUrl = $uploadResponse->json('upload_url');

        $supported = config('transcription.extras_supported_by_language');
        $langExtras = [
            'auto_chapters'     => in_array($language, $supported['auto_chapters'] ?? []),
            'summarization'     => in_array($language, $supported['summarization'] ?? []),
            'sentiment_analysis'=> in_array($language, $supported['sentiment_analysis'] ?? []),
            'entity_detection'  => in_array($language, $supported['entity_detection'] ?? []),
            'auto_highlights'   => in_array($language, $supported['auto_highlights'] ?? []),
            'content_safety'    => in_array($language, $supported['content_safety'] ?? []),
            'iab_categories'    => in_array($language, $supported['iab_categories'] ?? []),
        ];

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

        // Para archivos WebM largos, usar configuración minimalista y más robusta
        if ($isWebM) {
            $payload = [
                'audio_url' => $audioUrl,
                'language_code' => $language,
                'speaker_labels' => true,           // Activar para detección automática
                'punctuate' => true,               // Mantener puntuación básica
                'format_text' => false,            // Desactivar formato para reducir procesamiento
                'speech_threshold' => 0.5,         // Menos sensible para WebM
                'boost_param' => 'default',
                'filter_profanity' => false,
                'dual_channel' => false,
                'speed_boost' => false,            // CRÍTICO: Sin speed boost
                'auto_highlights' => false,
                'disfluencies' => false,
                'entity_detection' => false,
                'language_detection' => false,
                'multichannel' => false,
                'redact_pii' => false,
                'webhook_url' => null,
                'word_boost' => [],
                'audio_start_from' => null,
                'audio_end_at' => null,            // CRÍTICO: Sin límite de tiempo
                // No incluir speakers_expected para permitir detección automática
            ];

            Log::info('Applied MINIMAL WebM config with AUTO speaker detection', [
                'speaker_labels' => $payload['speaker_labels'],
                'format_text' => $payload['format_text'],
                'speed_boost' => $payload['speed_boost'],
                'speech_threshold' => $payload['speech_threshold'],
                'speakers_expected' => 'AUTO (not forced)',
                'audio_end_at' => $payload['audio_end_at'],
                'message' => 'Using minimal config with automatic speaker detection',
            ]);
        } else {
            $payload = $basePayload;
        }

        // Activar extras según soporte por idioma
        if ($langExtras['auto_chapters']) $payload['auto_chapters'] = true;
        if ($langExtras['summarization']) { $payload['summarization'] = true; $payload['summary_model'] = 'informative'; $payload['summary_type'] = 'bullets'; }
        if ($langExtras['sentiment_analysis']) $payload['sentiment_analysis'] = true;
        if ($langExtras['entity_detection']) $payload['entity_detection'] = true;
        if ($langExtras['auto_highlights']) $payload['auto_highlights'] = true;
        if ($langExtras['content_safety']) $payload['content_safety'] = true;
        if ($langExtras['iab_categories']) $payload['iab_categories'] = true;
        if (!array_filter($langExtras)) {
            Log::info('AssemblyAI extras disabled due to unsupported language', [ 'language' => $language ]);
        }

        $transcriptResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.assemblyai.com/v2/transcript', $payload);

        // Log del payload completo para debug en archivos WebM
        if ($isWebM) {
            Log::info('AssemblyAI payload sent for WebM file', [
                'payload' => $payload,
                'url' => 'https://api.assemblyai.com/v2/transcript',
            ]);
        }

        if (! $transcriptResponse->successful()) {
            throw new \Exception('AssemblyAI transcript creation failed: ' . $transcriptResponse->body());
        }

        return $transcriptResponse->json('id');
    }

    /**
     * Procesa y valida archivos WebM combinados
     * Intenta detectar corrupción y aplicar correcciones
     */
    private function processWebMFile(string $filePath, array $metadata): string
    {
        Log::info('Processing WebM file for integrity', [
            'file_path' => basename($filePath),
            'file_size' => filesize($filePath),
        ]);

        // Verificar integridad básica del archivo WebM
        $fileHandle = fopen($filePath, 'rb');
        $header = fread($fileHandle, 100);
        fclose($fileHandle);

        // Verificar header WebM
        $hasWebMHeader = strpos($header, 'webm') !== false ||
                        strpos($header, 'matroska') !== false ||
                        substr($header, 0, 4) === "\x1A\x45\xDF\xA3"; // EBML header

        if (!$hasWebMHeader) {
            Log::warning('WebM file appears to have corrupted header', [
                'header_preview' => bin2hex(substr($header, 0, 20)),
            ]);
        }

        // Intentar obtener duración real con ffprobe si está disponible
        $ffprobeCommand = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 \"$filePath\" 2>&1";
        $durationOutput = shell_exec($ffprobeCommand);

        if ($durationOutput && is_numeric(trim($durationOutput))) {
            $duration = floatval(trim($durationOutput));
            $minutes = round($duration / 60, 2);

            Log::info('WebM file duration detected by ffprobe', [
                'duration_seconds' => $duration,
                'duration_minutes' => $minutes,
            ]);

            // Si la duración es muy corta comparada con el tamaño del archivo, hay un problema
            $fileSizeMB = filesize($filePath) / 1024 / 1024;
            $expectedDurationForSize = $fileSizeMB * 2; // Muy rough estimate: 2 minutos por MB

            // Configuración: convertir solo si se detecta corrupción severa
            $shouldConvert = $minutes < 15 && $fileSizeMB > 50;

            Log::info('WebM integrity analysis', [
                'detected_minutes' => $minutes,
                'file_size_mb' => $fileSizeMB,
                'should_convert' => $shouldConvert,
                'reason' => $shouldConvert ? 'Potential corruption detected' : 'File appears healthy',
            ]);

            if ($shouldConvert) {
                Log::warning('WebM file may be corrupted - duration too short for file size', [
                    'detected_minutes' => $minutes,
                    'file_size_mb' => $fileSizeMB,
                    'expected_min_duration' => $expectedDurationForSize,
                ]);

                // Intentar conversión a WAV como último recurso
                $convertedPath = $this->convertWebMToWav($filePath);
                if ($convertedPath && file_exists($convertedPath)) {
                    Log::info('WebM successfully converted to WAV', [
                        'original_path' => basename($filePath),
                        'converted_path' => basename($convertedPath),
                    ]);
                    return $convertedPath;
                }

                // Crear una copia con nombre específico para debug
                $debugPath = dirname($filePath) . '/corrupted_webm_' . time() . '.webm';
                copy($filePath, $debugPath);

                Log::info('Created debug copy of potentially corrupted WebM', [
                    'debug_path' => $debugPath,
                ]);
            }
        } else {
            Log::warning('Could not determine WebM file duration - ffprobe not available or file corrupted');
        }

        return $filePath; // Por ahora, retornar el archivo original
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

    /**
     * Convierte WebM corrupto a WAV usando ffmpeg
     */
    private function convertWebMToWav(string $webmPath): ?string
    {
        $outputPath = dirname($webmPath) . '/converted_' . time() . '.wav';

        // Comando ffmpeg con parámetros robustos para archivos WebM problemáticos
        $ffmpegCommand = "ffmpeg -i \"$webmPath\" -acodec pcm_s16le -ar 16000 -ac 1 \"$outputPath\" 2>&1";

        Log::info('Attempting WebM to WAV conversion', [
            'input' => basename($webmPath),
            'output' => basename($outputPath),
            'command' => $ffmpegCommand,
        ]);

        $output = shell_exec($ffmpegCommand);

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            Log::info('WebM to WAV conversion successful', [
                'output_size' => filesize($outputPath),
                'ffmpeg_output' => $output,
            ]);
            return $outputPath;
        } else {
            Log::error('WebM to WAV conversion failed', [
                'ffmpeg_output' => $output,
            ]);
            return null;
        }
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
}

