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
        if ($systemMessage) {
            $messages[] = ['role' => 'system', 'content' => $systemMessage];
        }
        if (!empty($context)) {
            $messages[] = ['role' => 'system', 'content' => "Contexto adicional:\n" . implode("\n", $context)];
        }
        $messages = array_merge($messages, $history);

        $client = OpenAI::client(config('services.openai.api_key'));
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

        return [
            'content' => $content,
            'metadata' => [
                'model' => $response->model ?? null,
                'usage' => $usage,
                'processing_time' => $processingTime,
            ],
        ];
    }
}
