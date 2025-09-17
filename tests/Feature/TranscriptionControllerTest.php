<?php

namespace Tests\Feature;

use App\Services\AudioConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscriptionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.assemblyai.api_key', 'test-key');
    }

    public function test_wav_audio_is_converted_to_mp3_before_upload(): void
    {
        $fakeService = $this->createFakeConversionService();

        $this->app->instance(AudioConversionService::class, $fakeService);

        Http::fake([
            'https://api.assemblyai.com/v2/upload' => Http::response(['upload_url' => 'https://example.com/uploaded'], 200),
            'https://api.assemblyai.com/v2/transcript' => Http::response(['id' => 'transcript-id'], 200),
        ]);

        $wavFile = UploadedFile::fake()->createWithContent('sample.wav', 'wav audio', 'audio/wav');

        $response = $this->postJson('/transcription', [
            'audio' => $wavFile,
            'language' => 'es',
        ]);

        $response->assertOk()->assertJson(['id' => 'transcript-id']);

        $this->assertCount(1, $fakeService->calls);
        $this->assertSame('audio/wav', $fakeService->calls[0]['mime_type']);
        $this->assertTrue($fakeService->calls[0]['was_converted']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($fakeService) {
            if ($request->url() !== 'https://api.assemblyai.com/v2/upload') {
                return true;
            }

            $expectedBody = $fakeService->calls[0]['content'];
            return $request->body() === $expectedBody;
        });

        $fakeService->cleanup();
    }

    public function test_ogg_audio_is_converted_to_mp3_before_upload(): void
    {
        $fakeService = $this->createFakeConversionService();

        $this->app->instance(AudioConversionService::class, $fakeService);

        Http::fake([
            'https://api.assemblyai.com/v2/upload' => Http::response(['upload_url' => 'https://example.com/uploaded'], 200),
            'https://api.assemblyai.com/v2/transcript' => Http::response(['id' => 'transcript-id'], 200),
        ]);

        $oggFile = UploadedFile::fake()->createWithContent('sample.ogg', 'ogg audio', 'audio/ogg');

        $response = $this->postJson('/transcription', [
            'audio' => $oggFile,
            'language' => 'es',
        ]);

        $response->assertOk()->assertJson(['id' => 'transcript-id']);

        $this->assertCount(1, $fakeService->calls);
        $this->assertSame('audio/ogg', $fakeService->calls[0]['mime_type']);
        $this->assertTrue($fakeService->calls[0]['was_converted']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($fakeService) {
            if ($request->url() !== 'https://api.assemblyai.com/v2/upload') {
                return true;
            }

            $expectedBody = $fakeService->calls[0]['content'];
            return $request->body() === $expectedBody;
        });

        $fakeService->cleanup();
    }

    private function createFakeConversionService(): object
    {
        return new class extends AudioConversionService {
            public array $calls = [];
            private array $generated = [];

            public function convertToMp3(string $filePath, ?string $mimeType = null, ?string $originalExtension = null): array
            {
                $content = 'converted-mp3-from-' . ($originalExtension ?? 'unknown');
                $tempPath = tempnam(sys_get_temp_dir(), 'fake_mp3_');
                file_put_contents($tempPath, $content);
                $this->generated[] = $tempPath;

                $this->calls[] = [
                    'file_path' => $filePath,
                    'mime_type' => $mimeType,
                    'extension' => $originalExtension,
                    'was_converted' => true,
                    'content' => $content,
                    'returned_path' => $tempPath,
                ];

                return [
                    'path' => $tempPath,
                    'mime_type' => 'audio/mpeg',
                    'was_converted' => true,
                ];
            }

            public function cleanup(): void
            {
                foreach ($this->generated as $path) {
                    if (is_string($path) && file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
        };
    }
}
