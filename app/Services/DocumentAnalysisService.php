<?php

namespace App\Services;

use App\Models\AiDocument;
use App\Support\OpenAiConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentAnalysisService
{
    /**
     * Analiza el contenido extraído de un documento y genera un resumen junto con datos clave.
     *
     * @param  AiDocument  $document
     * @param  string  $text            Texto plano previamente extraído y normalizado.
     * @param  array{file_path?: string, max_chars?: int}  $options
     * @return array{
     *     summary: ?string,
     *     key_points: array<int, string>,
     *     topics: array<int, string>,
     *     language: ?string,
     *     model: ?string,
     *     reference_handle: string,
     *     raw_response?: ?string,
     *     source: string,
     *     fallback: bool
     * }
     */
    public function analyze(AiDocument $document, string $text, array $options = []): array
    {
        $handle = $this->generateReferenceHandle($document);
        $defaults = [
            'summary' => null,
            'key_points' => [],
            'topics' => [],
            'language' => null,
            'model' => null,
            'reference_handle' => $handle,
            'raw_response' => null,
            'source' => 'fallback',
            'fallback' => true,
        ];

        $cleanText = $this->sanitizeText($text);
        $maxChars = max(2000, (int) ($options['max_chars'] ?? 12000));
        $trimmedText = Str::limit($cleanText, $maxChars, '…');

        $apiKey = OpenAiConfig::apiKey();
        if (empty($apiKey) || trim($trimmedText) === '') {
            return array_merge($defaults, $this->fallbackSummary($document, $cleanText));
        }

        try {
            $client = \OpenAI::client($apiKey);
            $model = config('services.openai.document_analysis_model', 'gpt-4o-mini');

            $schema = [
                'name' => 'document_analysis',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string', 'description' => 'Resumen conciso del documento'],
                        'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'language' => ['type' => 'string', 'description' => 'Idioma predominante del documento'],
                    ],
                    'required' => ['summary'],
                ],
            ];

            $response = $client->chat()->create([
                'model' => $model,
                'response_format' => ['type' => 'json_schema', 'json_schema' => $schema],
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un analista documental experto. Resume la información de forma objetiva y clara.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($document, $handle, $trimmedText),
                    ],
                ],
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            $parsed = json_decode($content, true);

            if (is_array($parsed)) {
                return array_merge($defaults, [
                    'summary' => $this->cleanupSummary($parsed['summary'] ?? ''),
                    'key_points' => $this->normalizeStringArray($parsed['key_points'] ?? []),
                    'topics' => $this->normalizeStringArray($parsed['topics'] ?? []),
                    'language' => isset($parsed['language']) ? trim((string) $parsed['language']) : null,
                    'model' => $response->model ?? $model,
                    'raw_response' => $content,
                    'source' => 'openai',
                    'fallback' => false,
                    'reference_handle' => $handle,
                ]);
            }

            Log::warning('DocumentAnalysisService: respuesta no parseable', [
                'document_id' => $document->id,
                'response_content' => $content,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('DocumentAnalysisService: error al invocar OpenAI', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return array_merge($defaults, $this->fallbackSummary($document, $cleanText));
    }

    /**
     * Genera un identificador legible para mencionar el documento dentro del chat.
     */
    public function generateReferenceHandle(AiDocument $document): string
    {
        $base = $document->name
            ?? pathinfo((string) $document->original_filename, PATHINFO_FILENAME)
            ?? 'documento';

        $slug = Str::slug(Str::limit($base, 60, ''), '-');
        if ($slug === '') {
            $slug = 'documento';
        }

        return '@' . $slug . '-' . $document->id;
    }

    private function sanitizeText(string $text): string
    {
        return Str::of($text)
            ->replace(["\r\n", "\r"], "\n")
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ')
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }

    private function buildPrompt(AiDocument $document, string $handle, string $text): string
    {
        $meta = [
            'Título' => $document->name ?: $document->original_filename ?: ('Documento ' . $document->id),
            'Tipo' => $document->document_type ?: ($document->mime_type ?: 'desconocido'),
            'Referencia' => $handle,
            'Tamaño (bytes)' => $document->file_size ?: 'desconocido',
        ];

        $metaLines = collect($meta)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => $key . ': ' . $value)
            ->implode("\n");

        return <<<PROMPT
Analiza el siguiente documento y responde en español con un JSON que contenga:
- "summary": resumen descriptivo y conciso (4-6 oraciones) del contenido.
- "key_points": lista de ideas clave o hallazgos relevantes.
- "topics": etiquetas temáticas cortas si corresponde.
- "language": idioma predominante del documento.

Metadatos:
{$metaLines}

Contenido:
"""
{$text}
"""
PROMPT;
    }

    private function cleanupSummary(string $summary): ?string
    {
        $summary = trim($summary);
        if ($summary === '') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $summary) ?: $summary;
    }

    /**
     * Fallback heurístico si la llamada a OpenAI no es posible.
     */
    private function fallbackSummary(AiDocument $document, string $text): array
    {
        $handle = $this->generateReferenceHandle($document);
        $clean = trim($text);

        if ($clean === '') {
            $title = $document->name ?: $document->original_filename ?: ('Documento ' . $document->id);
            return [
                'summary' => sprintf('No se pudo generar un resumen automático para "%s" porque el contenido legible es muy limitado.', $title),
                'key_points' => [],
                'topics' => [],
                'language' => null,
                'reference_handle' => $handle,
                'source' => 'fallback',
                'fallback' => true,
            ];
        }

        $sentences = $this->splitSentences($clean);
        $summary = implode(' ', array_slice($sentences, 0, 3));
        if ($summary === '') {
            $summary = Str::limit($clean, 300, '…');
        }

        return [
            'summary' => $summary,
            'key_points' => array_slice($sentences, 0, 5),
            'topics' => [],
            'language' => null,
            'reference_handle' => $handle,
            'source' => 'fallback',
            'fallback' => true,
        ];
    }

    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        return array_values(array_filter(array_map(fn ($sentence) => trim($sentence), $parts)));
    }

    private function normalizeStringArray($value): array
    {
        return collect(Arr::wrap($value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}

