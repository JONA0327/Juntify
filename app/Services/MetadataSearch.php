<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MetadataSearch
{
    /**
     * Lightweight metadata-based search across user's documents.
     * Options: [limit?: int, session?: AiChatSession]
     * Returns array of fragments: ['text'=>string,'source_id'=>string,'content_type'=>'document_text','location'=>array]
     */
    public function search(string $username, string $query, array $options = []): array
    {
        $limit = max(1, (int) ($options['limit'] ?? 8));
        $session = ($options['session'] ?? null);
        $filterDocIds = array_values(array_unique(array_map('intval', array_filter(Arr::wrap($options['doc_ids'] ?? []), fn($v) => is_numeric($v)))));

        $docsQuery = AiDocument::byUser($username)->processed();

        // If session is documents, try to constrain to selected doc IDs
        if ($session instanceof AiChatSession && $session->context_type === 'documents') {
            $ids = Arr::wrap($session->context_data ?? []);
            if (!empty($ids)) {
                $docsQuery->whereIn('id', $ids);
            }
        }
        // If explicit filter provided, prioritize it
        if (!empty($filterDocIds)) {
            $docsQuery->whereIn('id', $filterDocIds);
        }

        /** @var Collection<int, AiDocument> $documents */
    $documents = $docsQuery->orderByDesc('updated_at')->limit(80)->get();

        if ($documents->isEmpty()) {
            return [];
        }

        $qTokens = $this->tokens($query);
        if (empty($qTokens)) {
            return [];
        }

        $candidates = [];

        foreach ($documents as $doc) {
            $meta = is_array($doc->document_metadata) ? $doc->document_metadata : [];
            $chunks = is_array($meta['chunks'] ?? null) ? $meta['chunks'] : [];

            if (!empty($chunks)) {
                foreach ($chunks as $chunk) {
                    $text = (string) ($chunk['snippet'] ?? '');
                    if ($text === '') continue;
                    $score = $this->score($qTokens, $this->tokens($text));
                    if ($score <= 0) continue;
                    $candidates[] = [
                        'score' => $score,
                        'doc' => $doc,
                        'index' => (int) ($chunk['index'] ?? 0),
                        'text' => $text,
                        'page' => $chunk['page'] ?? null,
                    ];
                }
            } elseif (!empty($doc->extracted_text)) {
                // Fallback: simple windowed search on extracted_text
                $pos = stripos($doc->extracted_text, $query);
                $snippet = $pos !== false
                    ? $this->window($doc->extracted_text, (int) $pos, 320)
                    : Str::limit($doc->extracted_text, 320);
                $score = $this->score($qTokens, $this->tokens($snippet));
                if ($score > 0) {
                    $candidates[] = [
                        'score' => $score,
                        'doc' => $doc,
                        'index' => 0,
                        'text' => $snippet,
                        'page' => null,
                    ];
                }
            }
        }

        if (empty($candidates)) {
            return [];
        }

        usort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $results = array_slice($candidates, 0, $limit);

        return array_map(function ($item) {
            /** @var AiDocument $doc */
            $doc = $item['doc'];
            $idx = (int) $item['index'];
            return [
                'text' => $item['text'],
                'source_id' => 'document:' . $doc->id . ':chunk:' . $idx,
                'content_type' => 'document_text',
                'location' => [
                    'type' => 'document',
                    'document_id' => (int) $doc->id,
                    'chunk_index' => $idx,
                    'page' => $item['page'] ?? null,
                    'title' => $doc->name ?: $doc->original_filename,
                    'url' => $doc->drive_file_id ? sprintf('https://drive.google.com/file/d/%s/view', $doc->drive_file_id) : null,
                ],
            ];
        }, $results);
    }

    private function tokens(string $text): array
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        // Remove very short tokens
        return array_values(array_filter($parts, fn ($t) => Str::length($t) >= 2));
    }

    private function score(array $qTokens, array $docTokens): float
    {
        if (empty($qTokens) || empty($docTokens)) return 0.0;
        $docSet = array_count_values($docTokens);
        $score = 0.0;
        foreach ($qTokens as $qt) {
            $score += (float) ($docSet[$qt] ?? 0);
        }
        // Normalize by length to avoid favoring huge chunks
        return $score / max(1, count($docTokens));
    }

    private function window(string $text, int $pos, int $size = 320): string
    {
        $start = max(0, $pos - (int) ($size / 2));
        $snippet = mb_substr($text, $start, $size);
        return trim($snippet);
    }
}
