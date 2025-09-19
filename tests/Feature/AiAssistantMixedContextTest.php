<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\EmbeddingSearch;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('ai assistant mixed context session returns meeting fragments', function () {
    $user = User::factory()->create(['username' => 'mixed-user']);
    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Reunión Mixta',
    ]);

    $capturedContext = null;

    $aiChatService = Mockery::mock(AiChatService::class);
    $aiChatService->shouldReceive('generateReply')
        ->once()
        ->andReturnUsing(function ($session, $systemMessage, $context) use (&$capturedContext) {
            $capturedContext = $context;

            return [
                'content' => 'Respuesta simulada',
                'metadata' => [
                    'context_fragments' => $context,
                ],
            ];
        });
    app()->instance(AiChatService::class, $aiChatService);

    $embeddingSearch = Mockery::mock(EmbeddingSearch::class);
    $embeddingSearch->shouldReceive('search')
        ->once()
        ->andReturn([]);
    app()->instance(EmbeddingSearch::class, $embeddingSearch);

    $this->withoutMiddleware([VerifyCsrfToken::class]);
    $this->actingAs($user);

    $sessionResponse = $this->postJson('/api/ai-assistant/sessions', [
        'context_type' => 'mixed',
        'context_id' => null,
        'context_data' => [
            'items' => [
                ['type' => 'meeting', 'id' => $meeting->id],
            ],
        ],
    ]);

    $sessionResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('session.context_type', 'mixed');
    $sessionId = $sessionResponse->json('session.id');

    $messageResponse = $this->postJson("/api/ai-assistant/sessions/{$sessionId}/messages", [
        'content' => '¿Qué sucedió en la reunión?'
    ]);

    $messageResponse->assertOk()->assertJsonPath('success', true);

    $assistantMessage = $messageResponse->json('assistant_message');

    expect($capturedContext)->not->toBeNull();

    $fragments = $assistantMessage['metadata']['context_fragments'] ?? [];
    expect($fragments)->not->toBeEmpty();

    $containsMeetingFragment = collect($fragments)->contains(function ($fragment) use ($meeting) {
        if (! is_array($fragment)) {
            return false;
        }

        $sourceId = $fragment['source_id'] ?? '';
        $citation = $fragment['citation'] ?? '';

        return str_contains($sourceId, 'meeting:' . $meeting->id)
            || str_contains($citation, 'meeting:' . $meeting->id);
    });

    expect($containsMeetingFragment)->toBeTrue();
});
