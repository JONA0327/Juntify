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
use function str_starts_with;

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
            'processing_progress' => 5,
            'processing_step' => 'preparing',
        ]);

        if ($this->isTemporaryDriveIdentifier($document->drive_file_id)) {
            $document->update([
                'processing_status' => 'failed',
                'processing_error' => 'El archivo todavía no está disponible en Google Drive. Intenta la carga nuevamente desde la aplicación.',
                'processing_step' => 'error',
                'processing_progress' => 0,
            ]);

            Log::warning('Skipping AI document processing due to temporary Drive identifier', [
                'document_id' => $document->id,
                'drive_file_id' => $document->drive_file_id,
            ]);

            return;
        }

        $tempFile = null;

        try {
            $token = $this->resolveAccessToken($document);
            if (! $token) {
                throw new RuntimeException('No hay credenciales válidas para acceder a Google Drive.');
            }

            $driveService->setAccessToken($token);
            $document->update(['processing_progress' => 10, 'processing_step' => 'downloading']);
            $fileContents = $driveService->downloadFileContent($document->drive_file_id);

            if ($fileContents === null) {
                throw new RuntimeException('No se pudo descargar el archivo desde Google Drive.');
            }

            $tempFile = $this->storeTemporaryFile($document, $fileContents);
            $document->update(['processing_progress' => 20, 'processing_step' => 'extracting']);

            $extracted = $extractorService->extract(
                $tempFile['absolute_path'],
                $document->mime_type ?? 'application/octet-stream',
                $document->original_filename
            );

            $text = $extracted['text'] ?? '';
            if (trim($text) === '') {
                throw new RuntimeException('El documento no contiene texto utilizable.');
            }

            $document->update(['processing_progress' => 60, 'processing_step' => 'chunking']);
            $chunkResult = $chunkerService->chunk($text);
            $chunks = $chunkResult['chunks'] ?? [];
            $normalizedText = $chunkResult['normalized_text'] ?? '';

            if (empty($chunks)) {
                throw new RuntimeException('No se pudieron generar fragmentos del documento.');
            }

            $useEmbeddings = (bool) env('AI_ASSISTANT_USE_EMBEDDINGS', false);
            $embeddingModel = config('services.openai.embedding_model', 'text-embedding-3-small');
            $embeddings = [];
            if ($useEmbeddings) {
                $document->update(['processing_progress' => 75, 'processing_step' => 'embedding']);
                $embeddings = $embeddingService->embedChunks($chunks, 20, $embeddingModel);
            } else {
                // Sin embeddings: pasamos a indexado ligero por metadatos
                $document->update(['processing_progress' => 75, 'processing_step' => 'indexing']);
            }

            DB::transaction(function () use ($document, $chunks, $normalizedText, $embeddings, $extracted, $embeddingModel, $useEmbeddings) {
                if ($useEmbeddings) {
                    AiContextEmbedding::where('content_type', 'document_text')
                        ->where('content_id', (string) $document->id)
                        ->where('username', $document->username)
                        ->delete();
                }

                // Construir chunks ligeros en metadatos para MetadataSearch
                $lightChunks = [];
                foreach ($chunks as $chunk) {
                    $index = (int) $chunk['index'];
                    $snippet = (string) ($chunk['normalized_text'] ?? '');
                    $page = $chunk['metadata']['page'] ?? null;
                    $lightChunks[] = [
                        'index' => $index,
                        'snippet' => $snippet,
                        'page' => $page,
                    ];
                    if ($useEmbeddings) {
                        $vector = $embeddings[$index] ?? null;
                        if (!empty($vector)) {
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
                                'content_snippet' => $snippet,
                                'embedding_vector' => $vector,
                                'metadata' => $metadata,
                            ]);
                        }
                    }
                }

                $document->update([
                    'extracted_text' => $normalizedText,
                    'ocr_metadata' => $extracted['metadata'] ?? [],
                    'document_metadata' => array_merge((array) $document->document_metadata ?: [], [
                        'chunks' => $lightChunks,
                    ]),
                    'processing_status' => 'completed',
                    'processing_error' => null,
                    'processing_progress' => 100,
                    'processing_step' => 'done',
                ]);
            });
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if (stripos($message, 'No se encontró ningún motor para extraer texto de PDF') !== false
                || stripos($message, 'El documento no contiene texto utilizable') !== false) {
                $message .= ' · Sugerencia: instala pdftotext/poppler, ghostscript y/o tesseract (spa+eng) en el servidor, o sube un PDF con texto seleccionable. Si es un escaneo, activa OCR.';
            }
            if (stripos($message, 'embeddings') !== false) {
                $message .= ' · Sugerencia: verifica OPENAI_API_KEY y permisos, el modelo de embeddings (config/services.php -> openai.embedding_model), y que el texto no esté vacío o sea demasiado largo por petición.';
            }
            $document->update([
                'processing_status' => 'failed',
                'processing_error' => $message,
                'processing_step' => 'error',
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

    private function isTemporaryDriveIdentifier(?string $driveId): bool
    {
        if (!is_string($driveId) || $driveId === '') {
            return true;
        }

        return str_starts_with($driveId, 'temp_')
            || str_starts_with($driveId, 'temp-');
    }
}
