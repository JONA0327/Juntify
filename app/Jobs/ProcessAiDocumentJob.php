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
            'processing_progress' => 5,
            'processing_step' => 'preparing',
        ]);

        $tempFile = null;

        try {
            if ($document->is_temporary && isset($document->document_metadata['file_content'])) {
                // Para archivos temporales, usar contenido guardado en metadata
                $document->update(['processing_progress' => 10, 'processing_step' => 'reading_temporary']);
                $fileContents = base64_decode($document->document_metadata['file_content']);

                if ($fileContents === false) {
                    throw new RuntimeException('No se pudo decodificar el contenido del archivo temporal.');
                }
            } else if ($document->is_temporary && isset($document->document_metadata['large_file']) && $document->document_metadata['large_file'] === true) {
                // Para archivos grandes temporales, procesar desde archivo
                $document->update(['processing_progress' => 10, 'processing_step' => 'reading_large_temporary']);
                $fileContents = $this->handleLargeTemporaryFile($document);

                if ($fileContents === false) {
                    throw new RuntimeException('No se pudo procesar el archivo temporal grande.');
                }
            } else {
                // Para archivos permanentes, descargar desde Google Drive
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
            }

            $document->update(['processing_progress' => 20, 'processing_step' => 'extracting']);

            if ($document->is_temporary) {
                // Para archivos temporales, extraer texto usando el contenido disponible
                $tempFile = $this->storeTemporaryFile($document, $fileContents);

                try {
                    $extracted = $extractorService->extract(
                        $tempFile['absolute_path'],
                        $document->mime_type ?? 'application/octet-stream',
                        $document->original_filename
                    );

                    $text = $extracted['text'] ?? '';

                    // Si no se extrajo texto, usar placeholder pero intentar OCR básico
                    if (trim($text) === '') {
                        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
                        $text = "[DOCUMENTO TEMPORAL] Archivo {$extension}: {$document->original_filename}\n";
                        $text .= "Tamaño: " . number_format($document->file_size) . " bytes\n";
                        $text .= "No se pudo extraer texto del documento. Puede requerir OCR manual.";

                        $extracted = [
                            'text' => $text,
                            'metadata' => [
                                'temporary_file' => true,
                                'file_type' => $extension,
                                'extraction_method' => 'temporary_fallback',
                                'chatgpt_ready' => false,
                                'extraction_failed' => true,
                            ]
                        ];
                    } else {
                        // Éxito en extracción
                        $extracted['metadata']['temporary_file'] = true;
                        $extracted['metadata']['chatgpt_ready'] = true;
                        $extracted['metadata']['extraction_method'] = 'temporary_extracted';
                    }

                } catch (\Exception $e) {
                    // Si falla la extracción, usar placeholder
                    $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
                    $text = "[DOCUMENTO TEMPORAL] Archivo {$extension}: {$document->original_filename}\n";
                    $text .= "Tamaño: " . number_format($document->file_size) . " bytes\n";
                    $text .= "Error en extracción: " . $e->getMessage();

                    $extracted = [
                        'text' => $text,
                        'metadata' => [
                            'temporary_file' => true,
                            'file_type' => $extension,
                            'extraction_method' => 'temporary_error',
                            'chatgpt_ready' => false,
                            'extraction_error' => $e->getMessage(),
                        ]
                    ];
                }

                // Limpiar archivo temporal
                $this->cleanupTemporaryFile($tempFile);

            } else {
                // Para archivos permanentes, usar extracción normal
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
            }

            $document->update(['processing_progress' => 60, 'processing_step' => 'chunking']);

            if ($document->is_temporary) {
                // Para archivos temporales, crear un chunk simple
                $chunks = [[
                    'index' => 0,
                    'text' => $text,
                    'normalized_text' => $text,
                    'tokens' => strlen($text) / 4, // Aproximación simple
                    'metadata' => [
                        'page' => 1,
                        'temporary_document' => true,
                        'ready_for_chatgpt' => true,
                    ]
                ]];
                $normalizedText = $text;
            } else {
                // Para archivos permanentes, usar chunking normal
                $chunkResult = $chunkerService->chunk($text);
                $chunks = $chunkResult['chunks'] ?? [];
                $normalizedText = $chunkResult['normalized_text'] ?? '';

                if (empty($chunks)) {
                    throw new RuntimeException('No se pudieron generar fragmentos del documento.');
                }
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
            if ($tempFile && !$document->is_temporary) {
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

    /**
     * Maneja archivos temporales grandes de manera optimizada para memoria
     */
    private function handleLargeTemporaryFile(AiDocument $document): string
    {
        Log::info('Procesando archivo temporal grande', [
            'document_id' => $document->id,
            'file_size' => $document->file_size
        ]);

        $metadata = $document->document_metadata ?? [];
        $filePath = $metadata['temp_file_path'] ?? $metadata['file_path'] ?? null;

        if (!$filePath || !file_exists($filePath)) {
            throw new RuntimeException('Archivo temporal grande no encontrado en: ' . $filePath);
        }

        // Leer el archivo en chunks para evitar problemas de memoria
        $fileContents = '';
        $handle = fopen($filePath, 'rb');

        if (!$handle) {
            throw new RuntimeException('No se pudo abrir el archivo temporal: ' . $filePath);
        }

        try {
            $chunkSize = 2 * 1024 * 1024; // 2MB chunks
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException('Error leyendo chunk del archivo');
                }
                $fileContents .= $chunk;
            }
        } finally {
            fclose($handle);
        }

        // Para archivos muy grandes, NO guardamos contenido base64 para evitar MySQL limits
        $base64Size = strlen($fileContents) * 4 / 3; // Tamaño aproximado en base64

        if ($base64Size > 16 * 1024 * 1024) { // >16MB en base64 puede causar problemas MySQL
            Log::info('Archivo muy grande - no se guardará contenido base64 para evitar límites MySQL', [
                'document_id' => $document->id,
                'content_size' => strlen($fileContents),
                'base64_size_mb' => round($base64Size / 1024 / 1024, 2)
            ]);

            // Solo actualizar metadata sin contenido base64
            unset($metadata['temp_file_path']);
            unset($metadata['file_path']);
            $metadata['large_file_processed'] = true;
            $metadata['content_too_large_for_db'] = true;
            $metadata['original_size_mb'] = round(strlen($fileContents) / 1024 / 1024, 2);

            $document->update([
                'document_metadata' => $metadata,
                'processing_progress' => 15,
                'processing_step' => 'large_file_no_db_storage'
            ]);
        } else {
            // Para archivos que caben en MySQL, guardar contenido base64
            $metadata['file_content'] = base64_encode($fileContents);
            unset($metadata['temp_file_path']);
            unset($metadata['file_path']);
            unset($metadata['large_file']);

            $document->update([
                'document_metadata' => $metadata,
                'processing_progress' => 15,
                'processing_step' => 'large_file_processed'
            ]);
        }

        // Limpiar archivo temporal del sistema
        if (file_exists($filePath)) {
            unlink($filePath);
            Log::info('Archivo temporal limpiado', ['file_path' => $filePath]);
        }

        return $fileContents;
    }
}
