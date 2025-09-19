<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AiChatSession;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\EmbeddingSearch;
use Illuminate\Support\Facades\Schema;

test('ai assistant meeting context works without key points table', function () {
    Schema::dropIfExists('key_points');

    $user = User::factory()->create(['username' => 'missing-key-points-user']);
    $meeting = createLegacyMeeting($user);

    $session = AiChatSession::query()->create([
        'username' => $user->username,
        'title' => 'Meeting Context',
        'context_type' => 'meeting',
        'context_id' => $meeting->id,
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
                'content' => 'Respuesta de prueba',
                'metadata' => [
                    'context_fragments' => $context,
                ],
            ];
        }
    });

    $this->withoutMiddleware([VerifyCsrfToken::class]);
    $this->actingAs($user);

    $response = $this->postJson("/api/ai-assistant/sessions/{$session->id}/messages", [
        'content' => 'Hola asistente',
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $session->id,
        'role' => 'assistant',
    ]);
});
