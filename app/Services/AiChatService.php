<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Support\OpenAiConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
// Use the SDK global entrypoint to avoid facade collisions

class AiChatService
{
    /**
     * Generate a reply from the LLM based on session history, system message and context
     *
     * @param  AiChatSession  $session
     * @param  string|null  $systemMessage
     * @param  array  $context
     * @return array{content:string,metadata:array}
     */
    public function generateReply(AiChatSession $session, ?string $systemMessage = null, array $context = []): array
    {
        // Limit chat history to avoid blowing the token window
        $historyLimit = (int) env('AI_ASSISTANT_HISTORY_LIMIT', 12);
        $historyMaxChars = (int) env('AI_ASSISTANT_HISTORY_TEXT_LIMIT', 2000);

        $history = AiChatMessage::where('session_id', $session->id)
            ->orderByDesc('created_at')
            ->limit(max(1, $historyLimit))
            ->get()
            ->reverse()
            ->map(function (AiChatMessage $m) use ($historyMaxChars) {
                $text = (string) $m->content;
                if ($historyMaxChars > 0 && Str::length($text) > $historyMaxChars) {
                    $text = Str::limit($text, $historyMaxChars, '…');
                }
                return [
                    'role' => $m->role,
                    'content' => $text,
                ];
            })->toArray();

        $messages = [];

        $groundingInstruction = $this->buildGroundingInstruction($systemMessage);
        if ($groundingInstruction !== null) {
            $messages[] = ['role' => 'system', 'content' => $groundingInstruction];
        }

        // Compact context to fit within reasonable limits
        $maxFragments = (int) env('AI_ASSISTANT_MAX_CONTEXT_FRAGMENTS', 24);
        $fragmentTextLimit = (int) env('AI_ASSISTANT_FRAGMENT_TEXT_LIMIT', 800);
        $includeContextJson = filter_var(env('AI_ASSISTANT_INCLUDE_CONTEXT_JSON', true), FILTER_VALIDATE_BOOLEAN);

    $compactedContext = $this->compactContext($session, $context, $maxFragments, $fragmentTextLimit);

        if ($includeContextJson && ! empty($compactedContext)) {
            $contextJson = json_encode($compactedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($contextJson !== false) {
                $messages[] = [
                    'role' => 'system',
                    'content' => "Contexto relevante (resumido):\n" . $contextJson,
                ];
            }
        }

        $messages = array_merge($messages, $history);

        $apiKey = OpenAiConfig::apiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Falta la API Key de OpenAI. Define OPENAI_API_KEY en .env');
        }
        $client = \OpenAI::client($apiKey);
        $start = microtime(true);
        $model = config('services.openai.chat_model', env('AI_ASSISTANT_MODEL', 'gpt-4o-mini'));
        $response = $client->chat()->create([
            'model' => $model,
            'messages' => $messages,
        ]);
        $processingTime = microtime(true) - $start;

    $content = $response->choices[0]->message->content ?? '';
    $content = $this->sanitizeOutput((string) $content);
        $usage = [
            'prompt_tokens' => $response->usage->prompt_tokens ?? null,
            'completion_tokens' => $response->usage->completion_tokens ?? null,
            'total_tokens' => $response->usage->total_tokens ?? null,
        ];

        $citations = $this->extractCitations($content, $context);
        // Fallback: si el modelo no incluyó marcadores [..], adjuntar algunas citas derivadas del contexto (no intrusivo)
        if (empty($citations) && !empty($context)) {
            $fallback = [];
            $max = min(5, count($context));
            for ($i = 0; $i < $max; $i++) {
                $frag = $context[$i];
                $marker = $frag['citation'] ?? ($frag['source_id'] ?? null);
                if (!$marker) { continue; }
                $fallback[] = [
                    'marker' => $marker,
                    'fragment' => $frag,
                ];
            }
            if (!empty($fallback)) { $citations = $fallback; }
        }

        return [
            'content' => $content,
            'metadata' => [
                'model' => $response->model ?? $model,
                'usage' => $usage,
                'processing_time' => $processingTime,
                'context_fragments' => $context,
                'citations' => $citations,
            ],
        ];
    }

    /**
     * Reduce context: keep the most relevant fragments (by similarity if present),
     * truncate text, and drop heavy metadata to lower token usage.
     *
     * @param  array<int, array<string, mixed>>  $context
     * @return array<int, array<string, mixed>>
     */
    private function compactContext(AiChatSession $session, array $context, int $maxFragments, int $fragmentTextLimit): array
    {
        if (empty($context)) {
            return [];
        }

        // If container context, use broader limits and ensure at least one overview per meeting
        if ($session->context_type === 'container') {
            $maxFragments = (int) env('AI_ASSISTANT_MAX_CONTEXT_FRAGMENTS_CONTAINER', 48);
            $fragmentTextLimit = (int) env('AI_ASSISTANT_FRAGMENT_TEXT_LIMIT_CONTAINER', max(400, $fragmentTextLimit));

            // Group by meeting
            $byMeeting = [];
            foreach ($context as $frag) {
                $meetingId = $this->extractMeetingId($frag);
                if ($meetingId === null) {
                    $byMeeting['__no_meeting'][] = $frag;
                } else {
                    $byMeeting[$meetingId][] = $frag;
                }
            }

            // Pick one overview per meeting if present
            $selected = [];
            $includeOverviews = filter_var(env('AI_ASSISTANT_INCLUDE_MEETING_OVERVIEWS', true), FILTER_VALIDATE_BOOLEAN);
            if ($includeOverviews) {
                foreach ($byMeeting as $meetingId => $list) {
                    // Prefer container_meeting or meeting_summary as the overview
                    $overview = null;
                    foreach ($list as $frag) {
                        $type = $frag['content_type'] ?? '';
                        if (in_array($type, ['container_meeting','meeting_summary'], true)) {
                            $overview = $frag; break;
                        }
                    }
                    if ($overview) {
                        $selected[] = $overview;
                    }
                }
            }

            // Merge the rest and continue with generic compacting
            // Avoid duplicates
            $selectedHashes = [];
            $unique = function($frag) use (&$selectedHashes) {
                $key = md5(json_encode([
                    $frag['citation'] ?? null,
                    $frag['source_id'] ?? null,
                    substr($frag['text'] ?? '', 0, 50),
                ]));
                if (isset($selectedHashes[$key])) return false;
                $selectedHashes[$key] = true;
                return true;
            };

            $selected = array_values(array_filter($selected, $unique));
            // Append the rest of fragments
            foreach ($context as $frag) {
                if (!$unique($frag)) continue;
                $selected[] = $frag;
            }

            $context = $selected;
        }

        $focusedSegments = [];
        $otherTranscriptionSegments = [];
        $nonTranscriptionFragments = [];

        foreach ($context as $index => $frag) {
            if (! is_array($frag)) {
                continue;
            }

            $metadata = $frag['metadata'] ?? [];
            $contentType = (string) ($frag['content_type'] ?? '');
            $isTranscription = ($metadata['transcription_segment'] ?? false)
                || Str::contains($contentType, 'transcription_segment')
                || Str::contains($contentType, 'meeting_transcription_segment');

            if ($isTranscription) {
                $frag['_original_index'] = $index; // Preservar el orden natural del diálogo
                if (!empty($metadata['focused_speaker'])) {
                    $focusedSegments[] = $frag;
                } else {
                    $otherTranscriptionSegments[] = $frag;
                }
                continue;
            }

            $nonTranscriptionFragments[] = $frag;
        }

        if (!empty($focusedSegments) || !empty($otherTranscriptionSegments)) {
            usort($focusedSegments, fn($a, $b) => ($a['_original_index'] ?? 0) <=> ($b['_original_index'] ?? 0));
            usort($otherTranscriptionSegments, fn($a, $b) => ($a['_original_index'] ?? 0) <=> ($b['_original_index'] ?? 0));

            $nonTranscriptionFragments = $this->sortFragmentsBySimilarity($nonTranscriptionFragments);

            $combined = array_merge($focusedSegments, $otherTranscriptionSegments);
            $remainingSlots = $maxFragments - count($combined);
            if ($remainingSlots > 0) {
                $combined = array_merge($combined, array_slice($nonTranscriptionFragments, 0, $remainingSlots));
            }

            $context = $combined;
        } else {
            $context = $this->sortFragmentsBySimilarity($context);
            $context = array_slice($context, 0, max(1, $maxFragments));
        }

        return array_map(function ($frag) use ($fragmentTextLimit) {
            if (isset($frag['_original_index'])) {
                unset($frag['_original_index']);
            }

            $text = (string) ($frag['text'] ?? '');
            if ($fragmentTextLimit > 0 && Str::length($text) > $fragmentTextLimit) {
                $text = Str::limit($text, $fragmentTextLimit, '…');
            }
            $loc = Arr::get($frag, 'location', []);
            $minimalLoc = [];
            if (is_array($loc)) {
                $minimalLoc['type'] = $loc['type'] ?? null;
                foreach (['title','name','meeting_id','document_id','chat_id','timestamp','page','url'] as $k) {
                    if (isset($loc[$k]) && $loc[$k] !== null && $loc[$k] !== '') {
                        $minimalLoc[$k] = $loc[$k];
                    }
                }
                $minimalLoc = array_filter($minimalLoc, fn($v) => $v !== null && $v !== '');
            }

            return array_filter([
                'text' => $text,
                'citation' => $frag['citation'] ?? null,
                'location' => $minimalLoc ?: null,
            ], fn($v) => $v !== null);
        }, $context);
    }

    private function sortFragmentsBySimilarity(array $fragments): array
    {
        usort($fragments, function ($a, $b) {
            $sa = is_numeric($a['similarity'] ?? null) ? (float) $a['similarity'] : -INF;
            $sb = is_numeric($b['similarity'] ?? null) ? (float) $b['similarity'] : -INF;
            if ($sa === $sb) {
                return 0;
            }

            return $sa < $sb ? 1 : -1;
        });

        return $fragments;
    }

    private function extractMeetingId(array $frag): ?string
    {
        $loc = $frag['location'] ?? [];
        if (is_array($loc) && isset($loc['meeting_id'])) {
            return (string) $loc['meeting_id'];
        }
        foreach (['citation','source_id'] as $k) {
            $v = $frag[$k] ?? '';
            if (is_string($v) && preg_match('/meeting:(\d+)/', $v, $m)) {
                return (string) $m[1];
            }
        }
        return null;
    }

    private function buildGroundingInstruction(?string $systemMessage): ?string
    {
        $sections = [];

        if ($systemMessage) {
            $sections[] = trim($systemMessage);
        }

        $sections[] = "Instrucciones de grounding:\n"
            . "- Utiliza exclusivamente los fragmentos proporcionados para sustentar afirmaciones factuales.\n"
            . "- Cita siempre la fuente usando la cadena exacta del campo \"citation\" entre corchetes, por ejemplo [doc:123 p.2].\n"
            . "- No inventes fuentes ni información; si los fragmentos no contienen la respuesta, indícalo explícitamente.\n"
            . "- Mantén la coherencia con los fragmentos y evita especulaciones.\n"
            . "- Presenta la respuesta en Markdown claro y limpio (títulos breves, listas con \"- \" y sin caracteres extraños o invisibles).";

        if (empty($sections)) {
            return null;
        }

        return implode("\n\n", $sections);
    }

    private function extractCitations(string $content, array $contextFragments): array
    {
        if (trim($content) === '') {
            return [];
        }

        preg_match_all('/\[([^\]]+)\]/u', $content, $matches);
        $markers = array_unique($matches[1] ?? []);

        if (empty($markers)) {
            return [];
        }

        $fragmentsByCitation = [];
        foreach ($contextFragments as $fragment) {
            $citation = $fragment['citation'] ?? null;
            if ($citation) {
                $fragmentsByCitation[$citation] = $fragment;
            }
        }

        $citations = [];
        foreach ($markers as $marker) {
            if (isset($fragmentsByCitation[$marker])) {
                $citations[] = [
                    'marker' => $marker,
                    'fragment' => $fragmentsByCitation[$marker],
                ];
            }
        }

        return $citations;
    }

    /**
     * Limpia y normaliza el texto de salida para que sea más presentable en la UI.
     */
    private function sanitizeOutput(string $text): string
    {
        // Reemplazos básicos de tipografía y espacios
        $map = [
            "\u{00A0}" => ' ',   // NBSP
            "\u{202F}" => ' ',   // NNBSP
            "\u{2009}" => ' ',   // Thin space
            "\u{200A}" => ' ',   // Hair space
            "\u{2002}" => ' ', "\u{2003}" => ' ', "\u{2004}" => ' ',
            "\u{2005}" => ' ', "\u{2006}" => ' ', "\u{2007}" => ' ', "\u{2008}" => ' ',
            "•" => '- ',
            "–" => '-',
            "—" => '-',
            "“" => '"', "”" => '"', "„" => '"',
            "‘" => "'", "’" => "'",
        ];
        $text = strtr($text, $map);

        // Eliminar caracteres invisibles de control (excepto \n y \t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        // Eliminar zero-width y BOMs
        $text = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $text);
        // Normalizar espacios finos restantes
        $text = preg_replace('/[\x{2000}-\x{200A}]/u', ' ', $text);

        // Estandarizar listas con guiones y un espacio
        $text = preg_replace('/^\s*[\-*]\s+/m', '- ', $text);
        // Quitar espacios en blanco al final de cada línea
        $text = preg_replace('/[ \t]+$/m', '', $text);
        // Reducir líneas en blanco repetidas a como mucho 1
        $text = preg_replace('/(\R){3,}/', "\n\n", $text);
        // Colapsar múltiples espacios en uno (sin afectar saltos de línea)
        $text = preg_replace('/[\t ]{2,}/', ' ', $text);

        // Recortes finales
        $text = trim($text);
        // Asegurar saltos de línea estilo \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return $text;
    }
}
