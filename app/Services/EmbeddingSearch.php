<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiContextEmbedding;
use App\Models\AiDocument;
use App\Models\Container;
use App\Support\OpenAiConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
// Avoid facade; use SDK global entrypoint

class EmbeddingSearch
{
    /**
     * Search stored context embeddings for a user and return the most relevant snippets.
     *
     * @param  array{limit?:int,session?:AiChatSession,content_types?:array<int,string>,content_ids?:array<string,array<int|string>>}  $options
     * @return array<int, array<string, mixed>>
     */
    public function search(string $username, string $query, array $options = []): array
    {
        $apiKey = OpenAiConfig::apiKey();
        if (! $apiKey) {
            return [];
        }

        try {
            $client = \OpenAI::client($apiKey);
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

        $limit = max(1, (int) ($options['limit'] ?? 5));
        $pool = (int) ($options['pool'] ?? env('AI_ASSISTANT_RETRIEVAL_POOL', 80));
        $pool = max($limit, $pool);
        $lambda = (float) ($options['lambda'] ?? env('AI_ASSISTANT_MMR_LAMBDA', 0.7));
        if ($lambda < 0.0 || $lambda > 1.0) {
            $lambda = 0.7;
        }

        $embeddingsQuery = AiContextEmbedding::byUser($username);

        if (! empty($options['content_types']) && is_array($options['content_types'])) {
            $embeddingsQuery->whereIn('content_type', $options['content_types']);
        }

        if (! empty($options['content_ids']) && is_array($options['content_ids'])) {
            $this->applyContentIdFilters($embeddingsQuery, $options['content_ids']);
        }

        if (isset($options['session']) && $options['session'] instanceof AiChatSession) {
            $this->applySessionFilters($embeddingsQuery, $options['session']);
        }

        /** @var Collection<int, AiContextEmbedding> $embeddings */
        $embeddings = $embeddingsQuery->get();

        if ($embeddings->isEmpty()) {
            return [];
        }

        $documents = $this->loadDocumentsForEmbeddings($embeddings);

        // Build candidate list with vectors and base similarities
        $candidates = $embeddings->map(function (AiContextEmbedding $item) use ($queryVector) {
            $vec = $item->embedding_vector ?? [];
            return [
                'model' => $item,
                'vector' => is_array($vec) ? array_values($vec) : [],
                'sim_q' => $this->cosineSimilarity($queryVector, is_array($vec) ? $vec : []),
            ];
        })->filter(function (array $row) {
            // Discard empty vectors
            return !empty($row['vector']);
        })->values()->all();

        if (empty($candidates)) {
            return [];
        }

        // Sort by similarity to query and take a dynamic pool to diversify from
        usort($candidates, function ($a, $b) {
            return ($b['sim_q'] <=> $a['sim_q']);
        });
        $shortlist = array_slice($candidates, 0, min($pool, count($candidates)));

        // Apply MMR to pick top-K diversified results
        $selectedIdx = $this->mmrSelect($shortlist, $limit, $lambda);
        if (empty($selectedIdx)) {
            // Fallback to top-K by similarity if MMR returns nothing
            $selected = array_slice($shortlist, 0, $limit);
        } else {
            $selected = array_map(fn ($i) => $shortlist[$i], $selectedIdx);
        }

        // Map to the public result shape
        $results = [];
        foreach ($selected as $row) {
            /** @var AiContextEmbedding $item */
            $item = $row['model'];
            $location = $this->resolveLocation($item, $documents);
            $results[] = [
                'text' => $item->content_snippet,
                'source_id' => (string) $item->content_id,
                'content_type' => $item->content_type,
                'location' => $location,
                'similarity' => $row['sim_q'],
                'metadata' => $item->metadata ?? [],
                'citation' => $this->buildCitation($item, $location),
            ];
        }

        return $results;
    }

    /**
     * Select K items via Maximal Marginal Relevance (MMR) to balance relevance and diversity.
     * Returns the indices (relative to $candidates) of selected items.
     *
     * @param  array<int, array{model: AiContextEmbedding, vector: array<int,float>, sim_q: float}>  $candidates
     * @return array<int,int>
     */
    private function mmrSelect(array $candidates, int $k, float $lambda = 0.7): array
    {
        $n = count($candidates);
        if ($n === 0 || $k <= 0) {
            return [];
        }

        // Always start with the most relevant item
        $selected = [];
        $selected[] = 0; // candidates are pre-sorted by sim_q desc before calling

        // Precompute pairwise similarities lazily with cache to avoid O(n^2) recompute cost
        $pairCache = [];
        $sim = function (int $i, int $j) use (&$pairCache, $candidates) {
            if ($i === $j) return 1.0;
            $key = $i < $j ? "$i-$j" : "$j-$i";
            if (!isset($pairCache[$key])) {
                $pairCache[$key] = $this->cosineSimilarity($candidates[$i]['vector'], $candidates[$j]['vector']);
            }
            return $pairCache[$key];
        };

        while (count($selected) < min($k, $n)) {
            $bestIdx = null;
            $bestScore = -INF;

            for ($i = 0; $i < $n; $i++) {
                if (in_array($i, $selected, true)) continue;

                // Diversity term: max similarity to any already selected item
                $maxSimToSelected = 0.0;
                foreach ($selected as $j) {
                    $maxSimToSelected = max($maxSimToSelected, $sim($i, $j));
                }

                // MMR score
                $score = $lambda * $candidates[$i]['sim_q'] - (1.0 - $lambda) * $maxSimToSelected;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $i;
                }
            }

            if ($bestIdx === null) {
                break;
            }
            $selected[] = $bestIdx;
        }

        sort($selected);
        return $selected;
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

    /**
     * @param  Collection<int, AiContextEmbedding>  $embeddings
     * @return Collection<int, AiDocument>
     */
    private function loadDocumentsForEmbeddings(Collection $embeddings): Collection
    {
        $documentIds = $embeddings->where('content_type', 'document_text')
            ->pluck('content_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($documentIds)) {
            return collect();
        }

        return AiDocument::whereIn('id', $documentIds)->get()->keyBy('id');
    }

    /**
     * @param  array<string, array<int|string>>  $contentIds
     */
    private function applyContentIdFilters(Builder $query, array $contentIds): void
    {
        $filters = [];
        foreach ($contentIds as $type => $ids) {
            $normalized = array_filter(array_map(fn ($id) => (string) $id, Arr::wrap($ids)));
            if (! empty($normalized)) {
                $filters[$type] = $normalized;
            }
        }

        if (empty($filters)) {
            return;
        }

        $query->where(function (Builder $builder) use ($filters) {
            foreach ($filters as $type => $ids) {
                $builder->orWhere(function (Builder $subQuery) use ($type, $ids) {
                    $subQuery->where('content_type', $type)
                        ->whereIn('content_id', $ids);
                });
            }
        });
    }

    private function applySessionFilters(Builder $query, AiChatSession $session): void
    {
        $filters = [];

        switch ($session->context_type) {
            case 'documents':
                $documentIds = Arr::wrap($session->context_data ?? []);
                if (! empty($documentIds)) {
                    $filters['document_text'] = array_map(fn ($id) => (string) $id, $documentIds);
                }
                break;

            case 'meeting':
                if ($session->context_id) {
                    $filters['meeting_transcript'] = [(string) $session->context_id];
                    $filters['meeting_summary'] = [(string) $session->context_id];
                }
                break;

            case 'contact_chat':
                if ($session->context_id) {
                    $filters['chat_message'] = [(string) $session->context_id];
                }
                break;

            case 'container':
                // If context_data already contains meeting ids, use them
                $relatedIds = Arr::wrap($session->context_data ?? []);
                if (! empty($relatedIds)) {
                    $normalized = array_map(fn ($id) => (string) $id, $relatedIds);
                    $filters['meeting_transcript'] = $normalized;
                    $filters['meeting_summary'] = $normalized;
                }

                // Also, when context_id is a container id, resolve its meetings directly
                if (! empty($session->context_id)) {
                    $containerId = (string) $session->context_id;
                    $filters['container_overview'][] = $containerId;

                    $meetingIds = $this->resolveContainerMeetingIds($session->username, $containerId);
                    if (! empty($meetingIds)) {
                        $filters['meeting_transcript'] = array_merge($filters['meeting_transcript'] ?? [], $meetingIds);
                        $filters['meeting_summary'] = array_merge($filters['meeting_summary'] ?? [], $meetingIds);
                    }
                }
                break;

            case 'mixed':
                $items = Arr::get($session->context_data ?? [], 'items', []);
                if (is_array($items)) {
                    $containerMeetingsCache = [];

                    foreach ($items as $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $type = $item['type'] ?? null;
                        $id = $item['id'] ?? null;

                        if (! $type || $id === null || $id === '') {
                            continue;
                        }

                        switch ($type) {
                            case 'meeting':
                                $meetingId = (string) $id;
                                $filters['meeting_transcript'][] = $meetingId;
                                $filters['meeting_summary'][] = $meetingId;
                                break;

                            case 'container':
                                $containerId = (string) $id;
                                $filters['container_overview'][] = $containerId;

                                if (! array_key_exists($containerId, $containerMeetingsCache)) {
                                    $containerMeetingsCache[$containerId] = $this->resolveContainerMeetingIds($session->username, $id);
                                }

                                $meetingIds = $containerMeetingsCache[$containerId];

                                if (! empty($meetingIds)) {
                                    $filters['meeting_transcript'] = array_merge($filters['meeting_transcript'] ?? [], $meetingIds);
                                    $filters['meeting_summary'] = array_merge($filters['meeting_summary'] ?? [], $meetingIds);
                                }
                                break;

                            case 'documents':
                            case 'document':
                                $filters['document_text'][] = (string) $id;
                                break;

                            case 'contact_chat':
                            case 'chat':
                                $filters['chat_message'][] = (string) $id;
                                break;
                        }
                    }
                }
                break;
        }

        if (! empty($filters)) {
            foreach ($filters as $type => $ids) {
                $filters[$type] = array_values(array_unique(array_map(fn ($id) => (string) $id, $ids)));
            }

            $this->applyContentIdFilters($query, $filters);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveContainerMeetingIds(string $username, $containerId): array
    {
        $container = Container::query()
            ->where('id', $containerId)
            ->where('username', $username)
            ->first();

        if (! $container) {
            return [];
        }

        return $container->meetings()
            ->where('username', $username)
            ->pluck('transcriptions_laravel.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  Collection<int, AiDocument>  $documents
     * @return array<string, mixed>
     */
    private function resolveLocation(AiContextEmbedding $embedding, Collection $documents): array
    {
        if ($embedding->content_type === 'document_text') {
            $document = $documents->get((int) $embedding->content_id);

            $metadata = $embedding->metadata ?? [];
            $chunkIndex = isset($metadata['chunk_index']) ? (int) $metadata['chunk_index'] : null;
            $startOffset = isset($metadata['start_offset']) ? (int) $metadata['start_offset'] : null;
            $page = null;
            $url = null;
            $title = null;

            if ($document) {
                $title = $document->name ?: $document->original_filename;
                $url = $document->drive_file_id ? sprintf('https://drive.google.com/file/d/%s/view', $document->drive_file_id) : null;
                $pageCount = $document->ocr_metadata['pages']
                    ?? $document->document_metadata['pages']
                    ?? null;
                $totalLength = $document->extracted_text ? mb_strlen($document->extracted_text) : null;
                $page = $this->estimateDocumentPage($startOffset, $totalLength, $pageCount);
            }

            return array_filter([
                'type' => 'document',
                'document_id' => (int) $embedding->content_id,
                'title' => $title,
                'chunk_index' => $chunkIndex,
                'page' => $page,
                'start_offset' => $startOffset,
                'url' => $url,
            ], fn ($value) => $value !== null);
        }

        return [
            'type' => $embedding->content_type,
            'content_id' => (string) $embedding->content_id,
        ];
    }

    private function estimateDocumentPage(?int $startOffset, ?int $totalLength, ?int $pageCount): ?int
    {
        if ($startOffset === null || $totalLength === null || $totalLength <= 0 || $pageCount === null || $pageCount <= 0) {
            return null;
        }

        $ratio = $startOffset / max(1, $totalLength);
        $page = (int) floor($ratio * $pageCount) + 1;

        return max(1, min($pageCount, $page));
    }

    private function buildCitation(AiContextEmbedding $embedding, array $location): string
    {
        if ($embedding->content_type === 'document_text') {
            $pageSuffix = isset($location['page']) ? ' p.' . $location['page'] : null;
            $chunkSuffix = isset($location['chunk_index']) ? ' sec.' . ((int) $location['chunk_index'] + 1) : null;
            $suffix = $pageSuffix ?? $chunkSuffix;

            return 'doc:' . $embedding->content_id . ($suffix ? ' ' . $suffix : '');
        }

        if (Str::startsWith($embedding->content_type, 'meeting')) {
            return 'meeting:' . $embedding->content_id;
        }

        if ($embedding->content_type === 'chat_message') {
            return 'chat:' . $embedding->content_id;
        }

        return $embedding->content_type . ':' . $embedding->content_id;
    }
}
