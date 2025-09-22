<?php

namespace App\Services;

use App\Support\OpenAiConfig;
use Illuminate\Support\Arr;
// Avoid facade; use SDK global entrypoint
use RuntimeException;
use Throwable;

class EmbeddingService
{
    /**
     * Generate embedding vectors for the provided chunks in batches.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array<int, float>>
     */
    public function embedChunks(array $chunks, int $batchSize = 10, ?string $model = null): array
    {
        if (empty($chunks)) {
            return [];
        }

        $apiKey = OpenAiConfig::apiKey();
        if (! $apiKey) {
            throw new RuntimeException('Falta la API key de OpenAI para generar embeddings.');
        }

        $modelName = $model ?? config('services.openai.embedding_model', 'text-embedding-3-small');
    $client = \OpenAI::client($apiKey);

        $vectors = [];
        $batches = array_chunk($chunks, max(1, $batchSize));

        foreach ($batches as $batch) {
            $inputs = [];
            $indexMap = [];

            foreach ($batch as $chunk) {
                $text = $chunk['normalized_text'] ?? '';
                if (! is_string($text) || trim($text) === '') {
                    continue;
                }

                $inputs[] = $text;
                $indexMap[] = $chunk['index'] ?? null;
            }

            if (empty($inputs)) {
                continue;
            }

            try {
                $response = $client->embeddings()->create([
                    'model' => $modelName,
                    'input' => $inputs,
                ]);
            } catch (Throwable $exception) {
                throw new RuntimeException('Error al generar embeddings: ' . $exception->getMessage(), 0, $exception);
            }

            $rows = $response->data ?? [];
            foreach ($rows as $position => $row) {
                $chunkIndex = $indexMap[$position] ?? $position;
                $embedding = $row->embedding ?? [];

                $vectors[$chunkIndex] = array_map(static fn ($value) => (float) $value, Arr::wrap($embedding));
            }
        }

        ksort($vectors);

        if (empty($vectors)) {
            throw new RuntimeException('La API devolvió 0 embeddings. Revisa OPENAI_API_KEY, el modelo de embeddings y los límites de contenido.');
        }

        return $vectors;
    }
}
