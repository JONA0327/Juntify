<?php

use App\Http\Controllers\AiAssistantController;
use App\Models\AiChatSession;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\SharedMeeting;
use App\Models\User;
use App\Services\MeetingJuCacheService;

it('builds meeting fragments when user has access via container', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $meeting = createLegacyMeeting($owner);

    $container = MeetingContentContainer::query()->create([
        'name' => 'Container Accesible',
        'description' => 'Container que otorga acceso',
        'username' => $viewer->username,
        'is_active' => true,
    ]);

    MeetingContentRelation::query()->create([
        'container_id' => $container->id,
        'meeting_id' => $meeting->id,
    ]);

    $session = AiChatSession::query()->create([
        'username' => $viewer->username,
        'title' => 'Sesión con reunión compartida por contenedor',
        'context_type' => 'meeting',
        'context_id' => $meeting->id,
        'context_data' => [],
        'is_active' => true,
        'last_activity' => now(),
    ]);

    $fakeCache = new class($meeting->id) extends MeetingJuCacheService {
        public function __construct(private int $targetMeeting)
        {
        }

        public function getCachedParsed(int $meetingId): ?array
        {
            if ($meetingId !== $this->targetMeeting) {
                return null;
            }

            return [
                'summary' => 'Resumen visible vía contenedor',
                'key_points' => [],
                'segments' => [
                    ['text' => 'Segmento clave desde el contenedor', 'speaker' => 'Ana'],
                ],
            ];
        }

        public function setCachedParsed(int $meetingId, array $parsed, ?string $transcriptDriveId = null): bool
        {
            return true;
        }
    };

    app()->instance(MeetingJuCacheService::class, $fakeCache);

    $this->actingAs($viewer);

    $controller = app(AiAssistantController::class);
    $method = new ReflectionMethod($controller, 'buildMeetingContextFragments');
    $method->setAccessible(true);

    $fragments = $method->invoke($controller, $session, '');

    $summary = collect($fragments)->firstWhere('content_type', 'meeting_summary');
    expect($summary)->not->toBeNull();
    expect($summary['text'])->toContain('Resumen visible');

    $segment = collect($fragments)->firstWhere('content_type', 'meeting_segment');
    expect($segment)->not->toBeNull();
    expect($segment['text'])->toContain('Segmento clave');
});

it('builds meeting fragments when user has accepted shared meeting access', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $meeting = createLegacyMeeting($owner);

    SharedMeeting::query()->create([
        'meeting_id' => $meeting->id,
        'shared_by' => $owner->id,
        'shared_with' => $viewer->id,
        'status' => 'accepted',
        'shared_at' => now(),
    ]);

    $session = AiChatSession::query()->create([
        'username' => $viewer->username,
        'title' => 'Sesión con reunión compartida aceptada',
        'context_type' => 'meeting',
        'context_id' => $meeting->id,
        'context_data' => [],
        'is_active' => true,
        'last_activity' => now(),
    ]);

    $fakeCache = new class($meeting->id) extends MeetingJuCacheService {
        public function __construct(private int $targetMeeting)
        {
        }

        public function getCachedParsed(int $meetingId): ?array
        {
            if ($meetingId !== $this->targetMeeting) {
                return null;
            }

            return [
                'summary' => 'Resumen accesible por reunión compartida',
                'key_points' => [],
                'segments' => [
                    ['text' => 'Segmento proveniente de la reunión compartida', 'speaker' => 'Luis'],
                ],
            ];
        }

        public function setCachedParsed(int $meetingId, array $parsed, ?string $transcriptDriveId = null): bool
        {
            return true;
        }
    };

    app()->instance(MeetingJuCacheService::class, $fakeCache);

    $this->actingAs($viewer);

    $controller = app(AiAssistantController::class);
    $method = new ReflectionMethod($controller, 'buildMeetingContextFragments');
    $method->setAccessible(true);

    $fragments = $method->invoke($controller, $session, '');

    $summary = collect($fragments)->firstWhere('content_type', 'meeting_summary');
    expect($summary)->not->toBeNull();
    expect($summary['text'])->toContain('Resumen accesible');

    $segment = collect($fragments)->firstWhere('content_type', 'meeting_segment');
    expect($segment)->not->toBeNull();
    expect($segment['text'])->toContain('Segmento proveniente');
});
