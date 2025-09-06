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

            $extension = pathinfo($metadata['filename'] ?? '', PATHINFO_EXTENSION);
            $extension = $extension ? ".{$extension}" : '';
            $finalFilePath = "{$uploadDir}/final_audio{$extension}";
            $finalFile = fopen($finalFilePath, 'wb');

            for ($i = 0; $i < $metadata['chunks_expected']; $i++) {
                $chunkPath = "{$uploadDir}/chunk_{$i}";
                $chunkData = file_get_contents($chunkPath);
                fwrite($finalFile, $chunkData);
            }

            fclose($finalFile);

            Log::info('Chunks combined successfully', [
                'upload_id' => $this->uploadId,
                'tracking_id' => $this->trackingId,
                'final_size' => filesize($finalFilePath),
                'expected_size' => $metadata['total_size'] ?? null,
            ]);

            $processedPath = $finalFilePath;

            $transcriptionId = $this->uploadToAssemblyAI($processedPath, $metadata['language']);

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
            throw new \Exception('AssemblyAI API key missing');
        }

        $timeout = (int) config('services.assemblyai.timeout', 300);
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

        $supportsExtras = $language === 'en';

        $payload = [
            'audio_url' => $audioUrl,
            'language_code' => $language,
            'speaker_labels' => true,
        ];

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
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.assemblyai.com/v2/transcript', $payload);

        if (! $transcriptResponse->successful()) {
            throw new \Exception('AssemblyAI transcript creation failed: ' . $transcriptResponse->body());
        }

        return $transcriptResponse->json('id');
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

