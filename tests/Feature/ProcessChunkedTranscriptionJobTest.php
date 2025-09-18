<?php

namespace Tests\Feature;

use App\Exceptions\FfmpegUnavailableException;
use App\Jobs\ProcessChunkedTranscription;
use App\Services\AudioConversionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessChunkedTranscriptionJobTest extends TestCase
{
    /**
     * @dataProvider audioExtensionProvider
     */
    public function test_chunked_audio_is_converted_to_mp3_before_upload(string $extension, string $mime): void
    {
        config([
            'services.assemblyai.api_key' => 'test-key',
            'services.assemblyai.timeout' => 60,
            'services.assemblyai.connect_timeout' => 10,
            'services.assemblyai.verify_ssl' => true,
        ]);

        Cache::flush();

        $uploadBodies = [];
        $transcriptResponses = [];

        Http::fake([
            'https://api.assemblyai.com/v2/upload' => function (Request $request) use (&$uploadBodies, $extension) {
                $uploadBodies[] = $request->body();

                return Http::response([
                    'upload_url' => 'https://example.com/audio.mp3',
                ], 200);
            },
            'https://api.assemblyai.com/v2/transcript' => function (Request $request) use (&$transcriptResponses, $extension) {
                $transcriptResponses[] = $request->data();

                return Http::response([
                    'id' => 'transcript-' . $extension,
                ], 200);
            },
        ]);

        $uploadId = (string) Str::uuid();
        $trackingId = (string) Str::uuid();
        $uploadDir = storage_path('app/temp-uploads/' . $uploadId);

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $chunks = ['first-' . $extension, 'second-' . $extension];
        $totalSize = 0;

        foreach ($chunks as $index => $content) {
            $chunkPath = $uploadDir . '/chunk_' . $index;
            file_put_contents($chunkPath, $content);
            $totalSize += strlen($content);
        }

        $metadata = [
            'upload_id' => $uploadId,
            'filename' => 'audio.' . $extension,
            'original_extension' => $extension,
            'original_mime_type' => $mime,
            'supported_extensions' => ['mp3', 'wav', 'm4a', 'flac', 'ogg', 'aac', 'webm'],
            'conversion_target' => 'mp3',
            'conversion_required' => $extension !== 'mp3',
            'total_size' => $totalSize,
            'language' => 'es',
            'chunks_expected' => count($chunks),
            'chunks_received' => count($chunks),
            'created_at' => now()->toISOString(),
        ];

        file_put_contents(
            $uploadDir . '/metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $convertedPath = $uploadDir . '/converted.mp3';

        $fakeService = new class($convertedPath) extends AudioConversionService {
            public array $calls = [];

            public function __construct(private string $targetPath)
            {
            }

            public function convertToMp3(string $filePath, ?string $mimeType = null, ?string $originalExtension = null): array
            {
                $this->calls[] = [
                    'file_path' => $filePath,
                    'mime_type' => $mimeType,
                    'original_extension' => $originalExtension,
                ];

                file_put_contents($this->targetPath, 'fake-mp3-' . ($originalExtension ?? 'unknown'));

                return [
                    'path' => $this->targetPath,
                    'mime_type' => 'audio/mpeg',
                    'was_converted' => ($originalExtension ?? '') !== 'mp3',
                ];
            }
        };

        app()->instance(AudioConversionService::class, $fakeService);

        $job = new ProcessChunkedTranscription($uploadId, $trackingId);
        $job->handle();

        $expectedFinalPath = $uploadDir . '/final_audio.' . $extension;

        $this->assertSame(
            $expectedFinalPath,
            $fakeService->calls[0]['file_path'] ?? null,
            'The conversion service should receive the combined file path.'
        );

        $this->assertSame(
            $mime,
            $fakeService->calls[0]['mime_type'] ?? null,
            'The conversion service should receive the original mime type.'
        );

        $this->assertSame(
            $extension,
            $fakeService->calls[0]['original_extension'] ?? null,
            'The conversion service should receive the original extension.'
        );

        $this->assertSame(['fake-mp3-' . $extension], $uploadBodies, 'The MP3 body should be uploaded to AssemblyAI.');
        $this->assertNotEmpty($transcriptResponses, 'The transcript request should be sent.');

        $cacheData = Cache::get('chunked_transcription:' . $trackingId);
        $this->assertSame('processing', $cacheData['status'] ?? null);
        $this->assertSame('transcript-' . $extension, $cacheData['transcription_id'] ?? null);

        $this->assertFileDoesNotExist($convertedPath, 'Temporary MP3 should be cleaned up after processing.');
        $this->assertDirectoryDoesNotExist($uploadDir, 'Temporary upload directory should be removed after processing.');

        app()->forgetInstance(AudioConversionService::class);
    }

    public function test_chunked_audio_uploads_original_when_ffmpeg_unavailable_for_supported_format(): void
    {
        config([
            'services.assemblyai.api_key' => 'test-key',
            'services.assemblyai.timeout' => 60,
            'services.assemblyai.connect_timeout' => 10,
            'services.assemblyai.verify_ssl' => true,
        ]);

        Cache::flush();

        $uploadBodies = [];
        $transcriptResponses = [];

        Http::fake([
            'https://api.assemblyai.com/v2/upload' => function (Request $request) use (&$uploadBodies) {
                $uploadBodies[] = $request->body();

                return Http::response([
                    'upload_url' => 'https://example.com/audio.m4a',
                ], 200);
            },
            'https://api.assemblyai.com/v2/transcript' => function (Request $request) use (&$transcriptResponses) {
                $transcriptResponses[] = $request->data();

                return Http::response([
                    'id' => 'transcript-m4a',
                ], 200);
            },
        ]);

        $uploadId = (string) Str::uuid();
        $trackingId = (string) Str::uuid();
        $uploadDir = storage_path('app/temp-uploads/' . $uploadId);

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $chunks = ['first-m4a', 'second-m4a'];
        $totalSize = 0;

        foreach ($chunks as $index => $content) {
            $chunkPath = $uploadDir . '/chunk_' . $index;
            file_put_contents($chunkPath, $content);
            $totalSize += strlen($content);
        }

        $metadata = [
            'upload_id' => $uploadId,
            'filename' => 'audio.m4a',
            'original_extension' => 'm4a',
            'original_mime_type' => 'audio/mp4',
            'supported_extensions' => ['mp3', 'wav', 'm4a', 'flac', 'ogg', 'aac', 'webm'],
            'conversion_target' => 'mp3',
            'conversion_required' => true,
            'total_size' => $totalSize,
            'language' => 'es',
            'chunks_expected' => count($chunks),
            'chunks_received' => count($chunks),
            'created_at' => now()->toISOString(),
        ];

        file_put_contents(
            $uploadDir . '/metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $fakeService = new class extends AudioConversionService {
            public array $calls = [];

            public function convertToMp3(string $filePath, ?string $mimeType = null, ?string $originalExtension = null): array
            {
                $this->calls[] = [
                    'file_path' => $filePath,
                    'mime_type' => $mimeType,
                    'original_extension' => $originalExtension,
                ];

                throw new FfmpegUnavailableException('ffmpeg not available');
            }
        };

        app()->instance(AudioConversionService::class, $fakeService);

        $job = new ProcessChunkedTranscription($uploadId, $trackingId);
        $job->handle();

        $expectedFinalPath = $uploadDir . '/final_audio.m4a';

        $this->assertSame(
            $expectedFinalPath,
            $fakeService->calls[0]['file_path'] ?? null,
            'The conversion service should receive the combined file path even when FFmpeg is missing.'
        );

        $this->assertSame(
            ['first-m4asecond-m4a'],
            $uploadBodies,
            'The original audio should be uploaded when FFmpeg is unavailable for a supported format.'
        );

        $this->assertNotEmpty($transcriptResponses, 'The transcript request should still be sent when falling back.');

        $cacheData = Cache::get('chunked_transcription:' . $trackingId);
        $this->assertSame('processing', $cacheData['status'] ?? null);
        $this->assertSame('transcript-m4a', $cacheData['transcription_id'] ?? null);

        $this->assertFileDoesNotExist($expectedFinalPath, 'Temporary combined audio should be cleaned up after processing.');
        $this->assertDirectoryDoesNotExist($uploadDir, 'Temporary upload directory should be removed after processing.');

        app()->forgetInstance(AudioConversionService::class);
    }

    public function audioExtensionProvider(): array
    {
        return [
            ['wav', 'audio/wav'],
            ['ogg', 'audio/ogg'],
        ];
    }
}
