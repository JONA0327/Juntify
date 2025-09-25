<?php

use App\Http\Controllers\AiAssistantController;
use App\Models\AiChatSession;
use App\Models\AiContainerJuCache;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\User;
use App\Services\ContainerJuCacheService;
use App\Services\MeetingJuCacheService;

it('stores a unified container payload when preloading a container', function () {
    $user = User::factory()->create(['username' => 'cache-user']);

    $container = MeetingContentContainer::query()->create([
        'name' => 'Contenedor Demo',
        'description' => 'Contenedor para pruebas de payload',
        'username' => $user->username,
        'is_active' => true,
    ]);

    $meetingA = createLegacyMeeting($user, ['meeting_name' => 'Reunión Alfa']);
    $meetingB = createLegacyMeeting($user, ['meeting_name' => 'Reunión Beta']);

    MeetingContentRelation::query()->create(['container_id' => $container->id, 'meeting_id' => $meetingA->id]);
    MeetingContentRelation::query()->create(['container_id' => $container->id, 'meeting_id' => $meetingB->id]);

    $normalizedPayloads = [
        $meetingA->id => [
            'summary' => 'Resumen Alfa',
            'key_points' => [['text' => 'KP Alfa']],
            'tasks' => [['tarea' => 'Tarea Alfa']],
            'segments' => [
                ['speaker' => 'Ana', 'text' => 'Segmento Alfa 1'],
                ['speaker' => 'Luis', 'text' => 'Segmento Alfa 2'],
            ],
        ],
        $meetingB->id => [
            'summary' => 'Resumen Beta',
            'key_points' => [['text' => 'KP Beta']],
            'tasks' => [['tarea' => 'Tarea Beta']],
            'segments' => [
                ['speaker' => 'Carlos', 'text' => 'Segmento Beta 1'],
            ],
        ],
    ];

    $fakeCache = new class($normalizedPayloads) extends MeetingJuCacheService {
        public function __construct(private array $payloads) {}

        public function getCachedParsed(int $meetingId): ?array
        {
            return $this->payloads[$meetingId] ?? null;
        }

        public function setCachedParsed(int $meetingId, array $parsed, ?string $transcriptDriveId = null, ?array $rawFull = null): bool
        {
            return true;
        }
    };

    app()->instance(MeetingJuCacheService::class, $fakeCache);

    $this->actingAs($user);

    $response = $this->postJson(route('api.ai-assistant.containers.preload', $container->id));
    $response->assertStatus(200)->assertJson(['success' => true, 'container_id' => $container->id]);

    $this->assertDatabaseCount('ai_container_ju_caches', 1);

    $cacheRow = AiContainerJuCache::where('container_id', $container->id)->firstOrFail();
    $payload = $cacheRow->payload;

    expect($payload['meetings'][strval($meetingA->id)]['data']['summary'] ?? null)->toBe('Resumen Alfa');
    expect($payload['meetings'][strval($meetingB->id)]['data']['key_points'][0]['text'] ?? null)->toBe('KP Beta');
    expect($response->json('checksum'))->toBe($cacheRow->checksum);
});

it('returns focused meeting fragments from consolidated payload', function () {
    $user = User::factory()->create(['username' => 'focus-user']);

    $container = MeetingContentContainer::query()->create([
        'name' => 'Contenedor Foco',
        'username' => $user->username,
        'is_active' => true,
    ]);

    $meeting = createLegacyMeeting($user, ['meeting_name' => 'Reunión Objetivo']);
    MeetingContentRelation::query()->create(['container_id' => $container->id, 'meeting_id' => $meeting->id]);

    $payload = [
        'version' => 1,
        'generated_at' => now()->toIso8601String(),
        'container' => [
            'id' => $container->id,
            'name' => $container->name,
            'meetings_count' => 1,
        ],
        'meetings_order' => [$meeting->id],
        'aggregated' => [
            'summary_lines' => ['Reunión Objetivo: Resumen demo'],
            'summary_text' => 'Resumen agregado de reuniones (1 reuniones):\nReunión Objetivo: Resumen demo',
            'key_points' => ['Reunión Objetivo: Punto clave'],
            'tasks' => [],
            'stats' => [
                'total_meetings' => 1,
                'total_segments' => 1,
                'total_key_points' => 1,
                'total_tasks' => 0,
            ],
        ],
        'meetings' => [
            strval($meeting->id) => [
                'meta' => [
                    'id' => $meeting->id,
                    'name' => $meeting->meeting_name,
                ],
                'data' => [
                    'summary' => 'Resumen demo del meeting',
                    'key_points' => [['text' => 'Punto clave']],
                    'segments' => [
                        ['speaker' => 'Ana', 'text' => 'Inicio del proyecto'],
                    ],
                ],
                'stats' => [
                    'segments' => 1,
                    'key_points' => 1,
                    'tasks' => 0,
                    'summary_length' => 24,
                ],
            ],
        ],
    ];

    /** @var ContainerJuCacheService $containerCache */
    $containerCache = app(ContainerJuCacheService::class);
    $containerCache->setCachedPayload($container->id, $payload);

    $session = AiChatSession::query()->create([
        'username' => $user->username,
        'title' => 'Sesión contenedor',
        'context_type' => 'container',
        'context_id' => $container->id,
        'context_data' => [],
        'is_active' => true,
        'last_activity' => now(),
    ]);

    $this->actingAs($user);
    $controller = app(AiAssistantController::class);
    $method = new \ReflectionMethod($controller, 'buildContainerContextFragments');
    $method->setAccessible(true);

    $fragments = $method->invoke($controller, $session, 'Detallame la reunión ' . $meeting->id);

    expect($fragments)->not()->toBeEmpty();
    $summaryFragment = collect($fragments)->firstWhere('content_type', 'meeting_summary');
    expect($summaryFragment['text'] ?? '')->toContain('Resumen demo del meeting');

    $segmentFragment = collect($fragments)->firstWhere('content_type', 'meeting_transcription_segment');
    expect($segmentFragment['text'] ?? '')->toContain('Ana: Inicio del proyecto');
});
