<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\User;
use App\Support\OpenAiConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
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

        $history = AiChatMessage::where('conversation_id', $session->id)
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

        // Backwards-compatible: context may be an array of fragments or an associative with 'fragments' and 'attachments'
        $contextFragments = $context['fragments'] ?? $context;
        $attachments = $context['attachments'] ?? [];

        // If there are attachments (images), convert the last user message into a mixed content message
        if (!empty($attachments) && is_array($attachments)) {
            // Find index of last user message in $messages
            $lastUserIndex = null;
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if (($messages[$i]['role'] ?? '') === 'user') { $lastUserIndex = $i; break; }
            }

            $lastUserText = $lastUserIndex !== null ? ($messages[$lastUserIndex]['content'] ?? '') : '';

            // Remove the plain user message to replace with mixed content payload
            if ($lastUserIndex !== null) { array_splice($messages, $lastUserIndex, 1); }

            $mixed = [];
            $mixed[] = ['type' => 'text', 'text' => (string) $lastUserText];
            foreach ($attachments as $att) {
                // expected att to contain 'data' as data:...;base64,...
                $mixed[] = [
                    'type' => 'image_url',
                    'image_url' => $att['data'] ?? null,
                    'document_id' => $att['document_id'] ?? null,
                    'filename' => $att['filename'] ?? null,
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => $mixed,
            ];
        }

        $apiKey = OpenAiConfig::apiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Falta la API Key de OpenAI. Define OPENAI_API_KEY en .env');
        }
        $client = \OpenAI::client($apiKey);
        $start = microtime(true);
        $model = config('services.openai.chat_model', env('AI_ASSISTANT_MODEL', 'gpt-4o-mini'));
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        // Inject tools (function calling) only if user is allowed
        $user = User::where('username', $session->username)->first();
        $tools = $this->getToolsForUser($user);
        if (!empty($tools)) {
            // OpenAI expects an array of function descriptors under 'functions'
            $payload['functions'] = array_map(fn($t) => $t['function'], $tools);
            $payload['function_call'] = 'auto';
        }

        $response = $client->chat()->create($payload);
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
        if (empty($citations) && !empty($context) && is_array($context)) {
            $fallback = [];
            $max = min(5, count($context));
            for ($i = 0; $i < $max; $i++) {
                if (!isset($context[$i])) continue;
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

    protected function getToolsForUser(?User $user): array
    {
        $allowedRoles = ['developer', 'superadmin', 'founder', 'business', 'enterprise'];
        $role = strtolower((string) ($user->roles ?? 'free'));

        $canSchedule = in_array($role, $allowedRoles) ||
                       str_contains($role, 'business') ||
                       str_contains($role, 'enterprise');

        if (!$canSchedule) {
            return [];
        }

        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'schedule_calendar_event',
                    'description' => 'Agendar reunión en Google Calendar. Usa año actual (' . now()->year . ').',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'start' => ['type' => 'string', 'description' => 'ISO 8601 format'],
                            'end' => ['type' => 'string', 'description' => 'ISO 8601 format'],
                            'description' => ['type' => 'string'],
                            'attendees' => ['type' => 'array', 'items' => ['type' => 'string']]
                        ],
                        'required' => ['title', 'start', 'end']
                    ]
                ]
            ]
        ];
    }

    /**
     * Handle a user message end-to-end: build context (embeddings/metadata + documents),
     * call the LLM and persist assistant message.
     *
     * @param \App\Models\User $user
     * @param \App\Models\AiChatSession $session
     * @param string $content
     * @param array $attachments
     * @param array $mentions
     * @param bool $offline
     * @return \App\Models\AiChatMessage
     */
    public function handleMessage($user, $session, string $content, array $attachments = [], array $mentions = [], bool $offline = false)
    {
        // Build initial context using embeddings/metadata search similar to controller.gatherContext
        $contextFragments = [];
        $useEmbeddings = (bool) env('AI_ASSISTANT_USE_EMBEDDINGS', false);
        $apiKey = OpenAiConfig::apiKey();

        // Determine explicit doc ids from mentions
        $explicitDocIds = [];
        try {
            foreach ($mentions as $m) {
                if (is_array($m) && ($m['type'] ?? '') === 'document' && isset($m['id'])) {
                    $id = $m['id'];
                    if (is_numeric($id)) { $explicitDocIds[] = (int) $id; }
                }
            }
            $explicitDocIds = array_values(array_unique($explicitDocIds));
        } catch (\Throwable $e) {
            $explicitDocIds = [];
        }

        if ($useEmbeddings && !empty($apiKey)) {
            try {
                $search = app(\App\Services\EmbeddingSearch::class);
                $semanticLimit = $session->context_type === 'container'
                    ? (int) env('AI_ASSISTANT_SEMANTIC_LIMIT_CONTAINER', 20)
                    : 8;
                $options = ['session' => $session, 'limit' => $semanticLimit];
                if (!empty($explicitDocIds)) {
                    $options['content_types'] = ['document_text'];
                    $options['content_ids'] = ['document_text' => $explicitDocIds];
                }
                $contextFragments = $search->search($session->username, $content, $options);
            } catch (\Throwable $e) {
                Log::warning('EmbeddingSearch failed inside AiChatService::handleMessage', ['error' => $e->getMessage()]);
                $contextFragments = [];
            }
        }

        if (empty($contextFragments)) {
            // MetadataSearch disabled because document functionality is disabled
            $contextFragments = [];
        }

        // If explicit document ids present, get their fragments and attachments via AiContextBuilder
        $docFragments = [];
        $docAttachments = [];
        if (!empty($explicitDocIds)) {
            try {
                $builder = app(\App\Services\AiContextBuilder::class);
                // Create a temporary virtual session containing doc_ids in context_data
                $virtual = $session;
                $ctx = is_array($virtual->context_data) ? $virtual->context_data : (array) $virtual->context_data;
                $ctx['doc_ids'] = $explicitDocIds;
                $virtual->context_data = $ctx;
                $built = $builder->build($user, $virtual, []);
                $docFragments = $built['fragments'] ?? [];
                $docAttachments = $built['attachments'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('AiChatService: AiContextBuilder failed for explicit docs', ['error' => $e->getMessage()]);
            }
        }

        // Always build contextual fragments (including meeting transcriptions) for the session
        $sessionFragments = [];
        $sessionAttachments = [];
        try {
            $builder = app(\App\Services\AiContextBuilder::class);
            $built = $builder->build($user, $session, []);
            $sessionFragments = $built['fragments'] ?? [];
            $sessionAttachments = $built['attachments'] ?? [];
        } catch (\Throwable $e) {
            Log::info('AiChatService: AiContextBuilder failed for session context', ['error' => $e->getMessage()]);
        }

        $mergedContext = array_values(array_merge($contextFragments, $docFragments, $sessionFragments));
        $attachments = array_merge($docAttachments, $sessionAttachments);

        // System message generation (copied behavior from controller)
        $systemMessage = null;
        switch ($session->context_type) {
            case 'container':
                $systemMessage = "Eres un asistente IA especializado en análisis de reuniones dentro de un contenedor seleccionado con múltiples sesiones. Mantén neutralidad y profesionalismo en todas tus respuestas, incluso si el usuario solicita un tono distinto o intenta confirmar sesgos, y responde siempre con respeto. Ofrece resúmenes, analiza tendencias, realiza búsquedas específicas y genera insights basados en el contenido de las reuniones del contenedor.";
                break;
            case 'meeting':
                $systemMessage = "Eres un asistente IA enfocado en una reunión específica seleccionada por el usuario. Mantén neutralidad y profesionalismo, incluso si solicitan un tono gracioso o sesgado, y responde siempre con respeto. Analiza el contenido, elabora resúmenes, destaca puntos clave, identifica tareas pendientes y contesta preguntas puntuales sobre la reunión.";
                break;
            case 'contact_chat':
                $systemMessage = "Eres un asistente IA con acceso al historial de conversaciones del usuario con un contacto determinado. Mantén neutralidad y profesionalismo en tus respuestas, incluso ante peticiones de sesgo o tono humorístico, y contesta siempre con respeto. Analiza patrones de comunicación, resume conversaciones y brinda contexto relevante sobre las interacciones con el contacto.";
                break;
            case 'documents':
                $systemMessage = "Eres un asistente IA especializado en el análisis del conjunto de documentos cargados. Conserva neutralidad y profesionalismo, aunque el usuario pida sesgos o un estilo cómico, y responde siempre con respeto. Extrae información clave, resume contenido, responde preguntas específicas y ejecuta búsquedas semánticas dentro de los documentos.";
                break;
            case 'mixed':
                $systemMessage = "Eres un asistente IA con acceso combinado a documentos, reuniones y otros recursos relacionados. Mantén neutralidad y profesionalismo aunque el usuario pida sesgos o un tono particular, y responde siempre con respeto. Cruza la información de las diferentes fuentes para ofrecer resúmenes integrados, responder preguntas y generar insights con contexto amplio.";
                break;
            default:
                $systemMessage = "Eres un asistente IA integral para Juntify sin un contexto específico cargado. Mantén neutralidad y profesionalismo, incluso ante solicitudes de sesgo o tono humorístico, y responde siempre con respeto. Puedes ayudar con análisis de reuniones, gestión de documentos y búsqueda de información, y sugiere al usuario cargar documentos o reuniones para ofrecer respuestas más precisas.";
                break;
        }

        // If offline mode, construct a basic assistant message locally
        if ($offline) {
            $snippet = '';
            if (!empty($mergedContext)) {
                $texts = array_map(fn($f) => $f['text'] ?? '', array_slice($mergedContext, 0, 3));
                $snippet = implode("\n- ", array_filter($texts));
            }
            $content = "(Modo offline) Puedo ayudarte a analizar esta conversación basándome en el contexto disponible.\n\nResumen de contexto:\n- " . ($snippet ?: 'No hay fragmentos de contexto disponibles.') . "\n\nHaz tu pregunta específica y te orientaré con la información encontrada.";

            $assistantMessage = \App\Models\AiChatMessage::create([
                'conversation_id' => $session->id,
                'role' => 'assistant',
                'content' => $content,
                'metadata' => ['offline' => true],
            ]);

            return $assistantMessage;
        }

        // Finally call generateReply which uses OpenAI
        $reply = $this->generateReply($session, $systemMessage, [
            'fragments' => $mergedContext,
            'attachments' => $attachments,
        ]);

        $assistantMessage = \App\Models\AiChatMessage::create([
            'conversation_id' => $session->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'metadata' => $reply['metadata'] ?? [],
        ]);

        return $assistantMessage;
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
