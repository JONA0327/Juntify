<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ChunkedUploadConcurrencyTest extends TestCase
{
    public function test_parallel_chunk_uploads_keep_metadata_consistent(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension is required to run this test.');
        }

        $uploadId = (string) Str::uuid();
        $totalChunks = 6;
        $uploadDir = storage_path("app/temp-uploads/{$uploadId}");

        File::makeDirectory($uploadDir, 0755, true, true);

        $metadata = [
            'upload_id' => $uploadId,
            'filename' => 'sample.mp3',
            'original_extension' => 'mp3',
            'original_mime_type' => 'audio/mpeg',
            'conversion_target' => 'mp3',
            'conversion_required' => false,
            'total_size' => 1024,
            'language' => 'es',
            'chunks_expected' => $totalChunks,
            'chunks_received' => 0,
            'created_at' => now()->toISOString(),
        ];

        File::put("{$uploadDir}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $children = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Failed to fork process for parallel upload test.');
            }

            if ($pid === 0) {
                $chunk = UploadedFile::fake()->createWithContent("chunk_{$i}.part", 'data-' . $i);

                $response = $this->postJson('/transcription/chunked/upload', [
                    'chunk' => $chunk,
                    'chunk_index' => $i,
                    'upload_id' => $uploadId,
                ]);

                exit($response->getStatusCode() === 200 ? 0 : 1);
            }

            $children[] = $pid;
        }

        foreach ($children as $childPid) {
            pcntl_waitpid($childPid, $status);
            $this->assertTrue(pcntl_wifexited($status), 'A parallel chunk upload did not exit cleanly.');
            $this->assertSame(0, pcntl_wexitstatus($status), 'A parallel chunk upload failed.');
        }

        $metadataRaw = File::get("{$uploadDir}/metadata.json");
        $this->assertIsString($metadataRaw);
        $this->assertJson($metadataRaw);

        $decoded = json_decode($metadataRaw, true);
        $this->assertIsArray($decoded);
        $this->assertSame($totalChunks, $decoded['chunks_received']);
        $this->assertSame($totalChunks, $decoded['chunks_expected']);

        File::deleteDirectory($uploadDir);
    }
}
