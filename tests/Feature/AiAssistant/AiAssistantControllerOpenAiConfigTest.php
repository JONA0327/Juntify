<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\EmbeddingSearch;

it('allows sending messages when the OpenAI key is set only in openai.php config', function () {
    config()->set('openai.api_key', 'test-key-from-openai-config');
    config()->set('services.openai.api_key', null);

    $user = User::factory()->create(['username' => 'openai-config-user']);

    $session = AiChatSession::query()->create([
        'username' => $user->username,
        'title' => 'General Context',
        'context_type' => 'general',
        'context_id' => null,
        'context_data' => [],
        'is_active' => true,
        'last_activity' => now(),
    ]);

    app()->instance(EmbeddingSearch::class, new class () {
        public function search(string $username, string $query, array $options = []): array
        {
            return [];
        }
    });

    app()->instance(AiChatService::class, new class () {
        public function generateReply(AiChatSession $session, ?string $systemMessage = null, array $context = []): array
        {
            return [
                'content' => 'Respuesta con clave en openai.php',
                'metadata' => [
                    'context_fragments' => $context,
                ],
            ];
        }
    });

    $this->withoutMiddleware([VerifyCsrfToken::class]);
    $this->actingAs($user);

    $response = $this->postJson("/api/ai-assistant/sessions/{$session->id}/messages", [
        'content' => 'Probemos con la clave en openai.php',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $session->id,
        'role' => 'assistant',
    ]);
});
