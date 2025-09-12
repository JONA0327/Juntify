<?php

namespace App\Services;

use App\Models\AiContextEmbedding;
use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingSearch
{
    /**
     * Search stored context embeddings for a user and return the most relevant snippets.
     */
    public function search(string $username, string $query, int $limit = 5): array
    {
        try {
            $client = OpenAI::client(config('services.openai.api_key'));
            $response = $client->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $query,
            ]);
            $queryVector = $response->data[0]->embedding ?? [];
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($queryVector)) {
            return [];
        }

        $embeddings = AiContextEmbedding::byUser($username)->get();

        $results = $embeddings->map(function (AiContextEmbedding $item) use ($queryVector) {
            $similarity = $this->cosineSimilarity($queryVector, $item->embedding_vector ?? []);
            return [
                'snippet' => $item->content_snippet,
                'similarity' => $similarity,
            ];
        })->sortByDesc('similarity')
            ->take($limit)
            ->pluck('snippet')
            ->toArray();

        return $results;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        foreach ($a as $i => $valA) {
            $valB = $b[$i] ?? 0.0;
            $dot += $valA * $valB;
            $magA += $valA * $valA;
            $magB += $valB * $valB;
        }

        if ($magA == 0.0 || $magB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
