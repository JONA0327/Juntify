<?php

use App\Http\Controllers\MeetingController;
use App\Models\User;
use function Pest\Laravel\actingAs;
use Illuminate\Support\Str;

it('downloads PDF with Spanish keys', function () {
    $user = User::factory()->create();
    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Reunión Prueba',
    ]);

    $spanish = [
        'resumen' => 'Resumen en español',
        'puntos_clave' => ['Punto 1', 'Punto 2'],
        'tareas' => [['text' => 'Tarea 1']],
        'transcripcion' => 'Texto de la transcripción',
        'participantes' => ['Ana', 'Luis'],
        'segmentos' => [
            ['timestamp' => '00:00 - 00:10', 'speaker' => 'Ana', 'text' => 'Hola'],
        ],
    ];

    $controller = new MeetingController();
    $reflect = new ReflectionClass(MeetingController::class);
    $extract = $reflect->getMethod('extractMeetingDataFromJson');
    $extract->setAccessible(true);
    $normalized = $extract->invoke($controller, $spanish);

    $process = $reflect->getMethod('processTranscriptData');
    $process->setAccessible(true);
    $processed = $process->invoke($controller, $normalized);

    actingAs($user, 'sanctum');
    $response = $this->post('/api/meetings/' . $meeting->id . '/download-pdf', [
        'meeting_name' => 'Reunión Prueba',
        'sections' => ['summary', 'key_points', 'tasks', 'transcription'],
        'data' => $processed,
    ]);

    $response->assertStatus(200);
    expect(Str::startsWith($response->getContent(), '%PDF'))->toBeTrue();
});

it('previews PDF when sections are missing', function () {
    $user = User::factory()->create();
    $meeting = createLegacyMeeting($user, [
        'meeting_name' => 'Reunión Prueba',
    ]);

    $spanish = [
        'resumen' => 'Resumen solo',
        'transcripcion' => 'Algo de texto',
    ];

    $controller = new MeetingController();
    $reflect = new ReflectionClass(MeetingController::class);
    $extract = $reflect->getMethod('extractMeetingDataFromJson');
    $extract->setAccessible(true);
    $normalized = $extract->invoke($controller, $spanish);

    $process = $reflect->getMethod('processTranscriptData');
    $process->setAccessible(true);
    $processed = $process->invoke($controller, $normalized);

    actingAs($user, 'sanctum');
    $response = $this->post('/api/meetings/' . $meeting->id . '/preview-pdf', [
        'meeting_name' => 'Reunión Prueba',
        'sections' => ['summary'],
        'data' => $processed,
    ]);

    $response->assertStatus(200);
    expect(Str::startsWith($response->getContent(), '%PDF'))->toBeTrue();
});

it('parses bracketed transcript lines into speaker segments with seconds precision', function () {
    $controller = new MeetingController();
    $reflect = new ReflectionClass(MeetingController::class);
    $process = $reflect->getMethod('processTranscriptData');
    $process->setAccessible(true);

    $processed = $process->invoke($controller, [
        'transcription' => implode("\n", [
            '[00:02 - 00:09] Abraham: Bien, buenas tardes ya. ¿Qué tal? ¿Cómo has estado?',
            '[00:10 - 00:12] Jonathan: Bien. ¿Mucho trabajo?'
        ]),
    ]);

    expect($processed['segments'])->toHaveCount(2)
        ->and($processed['segments'][0]['speaker'])->toBe('Abraham')
        ->and($processed['segments'][0]['timestamp'])->toBe('00:02 - 00:09')
        ->and($processed['segments'][0]['start'])->toBe(2)
        ->and($processed['segments'][0]['end'])->toBe(9)
        ->and($processed['segments'][1]['speaker'])->toBe('Jonathan')
        ->and($processed['segments'][1]['start'])->toBe(10)
        ->and($processed['segments'][1]['end'])->toBe(12);
});

