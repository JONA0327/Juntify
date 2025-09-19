<?php

namespace App\Services;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use OpenAI\Laravel\Facades\OpenAI;

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
        $history = AiChatMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiChatMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])->toArray();

        $messages = [];

        $groundingInstruction = $this->buildGroundingInstruction($systemMessage);
        if ($groundingInstruction !== null) {
            $messages[] = ['role' => 'system', 'content' => $groundingInstruction];
        }

        if (! empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($contextJson !== false) {
                $messages[] = [
                    'role' => 'system',
                    'content' => "Fragmentos de contexto (JSON):\n" . $contextJson,
                ];
            }
        }

        $messages = array_merge($messages, $history);

        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Falta la API Key de OpenAI. Define OPENAI_API_KEY en .env');
        }
        $client = OpenAI::client($apiKey);
        $start = microtime(true);
        $response = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
        ]);
        $processingTime = microtime(true) - $start;

        $content = $response->choices[0]->message->content ?? '';
        $usage = [
            'prompt_tokens' => $response->usage->prompt_tokens ?? null,
            'completion_tokens' => $response->usage->completion_tokens ?? null,
            'total_tokens' => $response->usage->total_tokens ?? null,
        ];

        $citations = $this->extractCitations($content, $context);

        return [
            'content' => $content,
            'metadata' => [
                'model' => $response->model ?? null,
                'usage' => $usage,
                'processing_time' => $processingTime,
                'context_fragments' => $context,
                'citations' => $citations,
            ],
        ];
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
            . "- Mantén la coherencia con los fragmentos y evita especulaciones.";

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
}
