<?php

namespace App\Jobs;

use App\Models\AiContextEmbedding;
use App\Models\AiDocument;
use App\Models\GoogleToken;
use App\Services\ChunkerService;
use App\Services\EmbeddingService;
use App\Services\ExtractorService;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessAiDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $documentId)
    {
    }

    public function handle(
        GoogleDriveService $driveService,
        ExtractorService $extractorService,
        ChunkerService $chunkerService,
        EmbeddingService $embeddingService
    ): void {
        $document = AiDocument::find($this->documentId);

        if (! $document) {
            return;
        }

        $document->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);

        $tempFile = null;

        try {
            $token = $this->resolveAccessToken($document);
            if (! $token) {
                throw new RuntimeException('No hay credenciales válidas para acceder a Google Drive.');
            }

            $driveService->setAccessToken($token);
            $fileContents = $driveService->downloadFileContent($document->drive_file_id);

            if ($fileContents === null) {
                throw new RuntimeException('No se pudo descargar el archivo desde Google Drive.');
            }

            $tempFile = $this->storeTemporaryFile($document, $fileContents);

            $extracted = $extractorService->extract(
                $tempFile['absolute_path'],
                $document->mime_type ?? 'application/octet-stream',
                $document->original_filename
            );

            $text = $extracted['text'] ?? '';
            if (trim($text) === '') {
                throw new RuntimeException('El documento no contiene texto utilizable.');
            }

            $chunkResult = $chunkerService->chunk($text);
            $chunks = $chunkResult['chunks'] ?? [];
            $normalizedText = $chunkResult['normalized_text'] ?? '';

            if (empty($chunks)) {
                throw new RuntimeException('No se pudieron generar fragmentos del documento.');
            }

            $embeddingModel = config('services.openai.embedding_model', 'text-embedding-3-small');
            $embeddings = $embeddingService->embedChunks($chunks, 20, $embeddingModel);

            if (empty($embeddings)) {
                throw new RuntimeException('No se obtuvieron embeddings para los fragmentos generados.');
            }

            DB::transaction(function () use ($document, $chunks, $normalizedText, $embeddings, $extracted, $embeddingModel) {
                AiContextEmbedding::where('content_type', 'document_text')
                    ->where('content_id', (string) $document->id)
                    ->where('username', $document->username)
                    ->delete();

                foreach ($chunks as $chunk) {
                    $index = $chunk['index'];
                    $vector = $embeddings[$index] ?? null;

                    if (empty($vector)) {
                        continue;
                    }

                    $metadata = array_merge(
                        $extracted['metadata'] ?? [],
                        $chunk['metadata'] ?? [],
                        [
                            'chunk_index' => $index,
                            'tokens' => $chunk['tokens'] ?? null,
                            'embedding_model' => $embeddingModel,
                            'source_filename' => $document->original_filename,
                        ]
                    );

                    AiContextEmbedding::create([
                        'username' => $document->username,
                        'content_type' => 'document_text',
                        'content_id' => (string) $document->id,
                        'content_snippet' => $chunk['normalized_text'],
                        'embedding_vector' => $vector,
                        'metadata' => $metadata,
                    ]);
                }

                $document->update([
                    'extracted_text' => $normalizedText,
                    'ocr_metadata' => $extracted['metadata'] ?? [],
                    'processing_status' => 'completed',
                    'processing_error' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if (stripos($message, 'No se encontró ningún motor para extraer texto de PDF') !== false) {
                $message .= ' · Sugerencia: instala pdftotext/poppler o tesseract en el servidor, o sube un PDF con texto real (no imagen). Si es un escaneo, activa OCR.';
            }
            $document->update([
                'processing_status' => 'failed',
                'processing_error' => $message,
            ]);

            Log::error('Failed to process AI document', [
                'document_id' => $document->id,
                'error' => $message,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->fail($exception);
        } finally {
            if ($tempFile) {
                $this->cleanupTemporaryFile($tempFile);
            }
        }
    }

    private function resolveAccessToken(AiDocument $document): ?array
    {
        if ($document->drive_type === 'organization') {
            $organization = $document->user?->organization;
            $token = $organization?->googleToken;

            if ($token) {
                $accessToken = $token->access_token;
                if (is_array($accessToken)) {
                    $accessToken = $accessToken['access_token'] ?? null;
                }

                if (! $accessToken) {
                    return null;
                }

                return [
                    'access_token' => $accessToken,
                    'refresh_token' => $token->refresh_token,
                ];
            }
        }

        $token = GoogleToken::where('username', $document->username)->first();
        if ($token && $token->hasValidAccessToken()) {
            return $token->getTokenArray();
        }

        return null;
    }

    private function storeTemporaryFile(AiDocument $document, string $contents): array
    {
        $extension = strtolower(pathinfo($document->original_filename ?? '', PATHINFO_EXTENSION)) ?: 'tmp';
        $directory = 'ai-documents/' . $document->id;
        $filename = Str::uuid()->toString() . '.' . $extension;
        $relativePath = $directory . '/' . $filename;

        Storage::disk('local')->put($relativePath, $contents);

        return [
            'relative_path' => $relativePath,
            'absolute_path' => storage_path('app' . DIRECTORY_SEPARATOR . $relativePath),
        ];
    }

    private function cleanupTemporaryFile(array $tempFile): void
    {
        $relativePath = $tempFile['relative_path'] ?? null;
        if ($relativePath) {
            Storage::disk('local')->delete($relativePath);
            $directory = dirname($relativePath);
            if ($directory && $directory !== '.') {
                $files = Storage::disk('local')->files($directory);
                $directories = Storage::disk('local')->directories($directory);
                if (empty($files) && empty($directories)) {
                    Storage::disk('local')->deleteDirectory($directory);
                }
            }
        }
    }
}
