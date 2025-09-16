<?php

namespace App\Services;

use Illuminate\Support\Str;

class ChunkerService
{
    /**
     * Chunk normalized text into overlapping windows suitable for embeddings.
     *
     * @param  array{chunk_size?: int, chunk_overlap?: int}  $options
     * @return array{normalized_text: string, chunks: array<int, array<string, mixed>>}
     */
    public function chunk(string $text, array $options = []): array
    {
        $normalized = $this->normalizeText($text);

        $chunkSize = max(200, (int) ($options['chunk_size'] ?? 1500));
        $overlap = (int) ($options['chunk_overlap'] ?? 200);
        $overlap = max(0, min($overlap, $chunkSize - 1));

        $chunks = [];
        $length = mb_strlen($normalized);
        $offset = 0;
        $index = 0;

        while ($offset < $length) {
            $segment = mb_substr($normalized, $offset, $chunkSize);

            if ($segment === '') {
                break;
            }

            $segment = $this->balanceChunk($segment, $offset + $chunkSize < $length);
            $segment = trim($segment);

            if ($segment === '') {
                $offset += max(1, $chunkSize - $overlap);
                continue;
            }

            $actualLength = mb_strlen($segment);

            $chunks[] = [
                'index' => $index,
                'normalized_text' => $segment,
                'tokens' => $this->estimateTokens($segment),
                'metadata' => [
                    'start_offset' => $offset,
                    'end_offset' => $offset + $actualLength,
                    'length' => $actualLength,
                ],
            ];

            $offset += max(1, $chunkSize - $overlap);
            $index++;
        }

        return [
            'normalized_text' => $normalized,
            'chunks' => $chunks,
        ];
    }

    /**
     * Attempt to cut the chunk at a natural boundary when possible.
     */
    private function balanceChunk(string $segment, bool $hasMore): string
    {
        if (! $hasMore) {
            return $segment;
        }

        $breakpoints = ["\n\n", "\n", '. '];
        foreach ($breakpoints as $breakpoint) {
            $position = mb_strrpos($segment, $breakpoint);
            if ($position !== false && $position > mb_strlen($segment) * 0.5) {
                return mb_substr($segment, 0, $position + mb_strlen($breakpoint));
            }
        }

        return $segment;
    }

    private function normalizeText(string $text): string
    {
        return Str::of($text)
            ->replace(["\r\n", "\r"], "\n")
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ')
            ->replaceMatches('/[ \t\f\v]+/', ' ')
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }

    private function estimateTokens(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text));
        $wordCount = is_array($words) ? count(array_filter($words)) : 0;
        $charCount = mb_strlen($text);

        // Approximate GPT tokens: average 4 characters per token.
        $estimateFromChars = (int) ceil($charCount / 4);

        return max(1, max($wordCount, $estimateFromChars));
    }
}
