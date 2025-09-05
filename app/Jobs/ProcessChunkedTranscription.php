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

        try {
            $metadata = json_decode(file_get_contents($metadataPath), true);

            $finalFilePath = "{$uploadDir}/final_audio";
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

            $transcriptionId = $this->uploadToAssemblyAI($finalFilePath, $metadata['language']);

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
            $this->cleanupTempFiles($uploadDir);
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

        $transcriptResponse = Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url' => $audioUrl,
                'language_code' => $language,
                'speaker_labels' => true,
                'auto_chapters' => true,
                'summarization' => true,
                'summary_model' => 'informative',
                'summary_type' => 'bullets',
            ]);

        if (! $transcriptResponse->successful()) {
            throw new \Exception('AssemblyAI transcript creation failed: ' . $transcriptResponse->body());
        }

        return $transcriptResponse->json('id');
    }

    private function cleanupTempFiles(string $uploadDir): void
    {
        try {
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

