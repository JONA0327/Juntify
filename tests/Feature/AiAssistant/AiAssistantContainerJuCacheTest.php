<?php

use App\Http\Controllers\AiAssistantController;
use App\Models\AiChatSession;
use App\Models\AiMeetingJuCache;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\User;
use App\Services\MeetingJuCacheService;

it('caches ju data for every meeting when container fragments exceed the global limit', function () {
    $user = User::factory()->create(['username' => 'ju-cache-user']);

    $container = MeetingContentContainer::query()->create([
        'name' => 'Container con muchas reuniones',
        'description' => 'Usado para probar cache de ju',
        'username' => $user->username,
        'is_active' => true,
    ]);

    $meetingCount = 35; // 35 reuniones * 8 fragmentos por reunión > límite global 240
    $meetings = collect();
    for ($i = 0; $i < $meetingCount; $i++) {
        $meetings->push(createLegacyMeeting($user, [
            'meeting_name' => 'Reunión ' . ($i + 1),
            'created_at' => now()->subDays($i),
            'updated_at' => now()->subDays($i),
        ]));
    }

    foreach ($meetings as $meeting) {
        MeetingContentRelation::query()->create([
            'container_id' => $container->id,
            'meeting_id' => $meeting->id,
        ]);
    }

    $session = AiChatSession::query()->create([
        'username' => $user->username,
        'title' => 'Sesión con contexto de contenedor',
        'context_type' => 'container',
        'context_id' => $container->id,
        'context_data' => [],
        'is_active' => true,
        'last_activity' => now(),
    ]);

    $fakeCache = new class extends MeetingJuCacheService {
        public array $called = [];

        public function getCachedParsed(int $meetingId): ?array
        {
            $this->called[] = $meetingId;

            $row = AiMeetingJuCache::firstOrNew(['meeting_id' => $meetingId]);
            $row->transcript_drive_id = 'fake-drive-' . $meetingId;
            $row->data = [
                'summary' => 'Resumen ' . $meetingId,
                'key_points' => ['Punto clave ' . $meetingId],
                'tasks' => [],
                'transcription' => '',
                'speakers' => [],
                'segments' => [],
            ];
            $row->save();

            return $row->data;
        }

        public function setCachedParsed(int $meetingId, array $parsed, ?string $transcriptDriveId = null): bool
        {
            return true;
        }
    };

    app()->instance(MeetingJuCacheService::class, $fakeCache);

    $this->actingAs($user);

    $controller = app(AiAssistantController::class);
    $method = new \ReflectionMethod($controller, 'buildContainerContextFragments');
    $method->setAccessible(true);
    $method->invoke($controller, $session, '');

    foreach ($meetings as $meeting) {
        $this->assertDatabaseHas('ai_meeting_ju_caches', [
            'meeting_id' => $meeting->id,
        ]);
    }

    expect(array_unique($fakeCache->called))->toHaveCount($meetingCount);
});

it('persists generated transcript when ju payload only includes segments', function () {
    $user = User::factory()->create();
    $meeting = createLegacyMeeting($user);

    $segments = [
        [
            'timestamp' => '00:00 - 00:05',
            'speaker' => 'Ana',
            'text' => 'Bienvenidos al proyecto',
        ],
        [
            'start' => 5,
            'end' => 12,
            'speaker' => 'Luis',
            'text' => 'Gracias Ana',
        ],
        [
            'start' => 12,
            'speaker' => 'Ana',
            'text' => 'Continuemos con la agenda',
        ],
    ];

    $payload = [
        'summary' => 'Resumen breve',
        'key_points' => ['Introducción'],
        'tasks' => [],
        'speakers' => ['Ana', 'Luis'],
        'segments' => $segments,
    ];

    $parser = new class {
        use \App\Traits\MeetingContentParsing;

        public function normalize(array $data): array
        {
            return $this->processTranscriptData($data);
        }
    };

    $normalized = $parser->normalize($payload);

    $expectedTranscript = implode("\n", [
        '[00:00 - 00:05] Ana: Bienvenidos al proyecto',
        '[00:05 - 00:12] Luis: Gracias Ana',
        '[00:12] Ana: Continuemos con la agenda',
    ]);

    expect($normalized['transcription'])->toBe($expectedTranscript);

    /** @var MeetingJuCacheService $service */
    $service = app(MeetingJuCacheService::class);
    $service->setCachedParsed((int) $meeting->id, $normalized, (string) $meeting->transcript_drive_id);

    $cached = AiMeetingJuCache::where('meeting_id', $meeting->id)->firstOrFail();
    expect($cached->data['transcription'])->toBe($expectedTranscript);
});
