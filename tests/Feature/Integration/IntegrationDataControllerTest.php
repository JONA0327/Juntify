<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('returns integration meeting details with tokenized stream url and raw ju data', function () {
    Storage::fake('local');

    $juData = [
        'summary' => 'Resumen de prueba',
        'key_points' => ['Primer punto', 'Segundo punto'],
        'tasks' => [
            ['title' => 'Enviar follow-up', 'owner' => 'Ana'],
        ],
        'transcription' => 'Texto completo de la reunión.',
        'speakers' => [['name' => 'Ana']],
        'segments' => [
            ['speaker' => 'Ana', 'text' => 'Hola a todos', 'start' => 0, 'end' => 5],
        ],
    ];

    Http::fake([
        'https://example.test/transcript.ju' => Http::response(json_encode($juData), 200, [
            'Content-Type' => 'application/json',
        ]),
        'https://example.test/audio.ogg' => Http::response('AUDIO', 200, [
            'Content-Type' => 'audio/ogg',
            'Content-Disposition' => 'attachment; filename="audio.ogg"',
        ]),
    ]);

    $user = User::factory()->create([
        'username' => 'integration-user',
    ]);

    $meeting = createLegacyMeeting($user, [
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
        'audio_download_url' => 'https://example.test/audio.ogg',
        'transcript_download_url' => 'https://example.test/transcript.ju',
    ]);

    $plainToken = 'plain-api-token';
    ApiToken::create([
        'user_id' => $user->id,
        'name' => 'Test Token',
        'token_hash' => ApiToken::hashToken($plainToken),
        'abilities' => ['meetings:read'],
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $plainToken,
    ])->getJson('/api/integrations/meetings/' . $meeting->id);

    $response->assertOk();

    $data = $response->json('data');

    expect($data['audio']['stream_url'])
        ->toContain('/api/integrations/meetings/' . $meeting->id . '/audio')
        ->and($data['audio']['stream_url'])
        ->toContain('api_token=' . $plainToken);

    expect($data['ju']['raw'])
        ->toMatchArray($juData);

    expect($data['ju']['needs_encryption'])->toBeTrue();
});

it('streams meeting audio when api token is provided via query parameter', function () {
    Storage::fake('local');

    Http::fake([
        'https://example.test/audio.ogg' => Http::response('AUDIOBYTES', 200, [
            'Content-Type' => 'audio/ogg',
            'Content-Disposition' => 'inline; filename="stream.ogg"',
        ]),
    ]);

    $user = User::factory()->create([
        'username' => 'stream-user',
    ]);

    $meeting = createLegacyMeeting($user, [
        'audio_drive_id' => null,
        'audio_download_url' => 'https://example.test/audio.ogg',
        'transcript_drive_id' => null,
        'transcript_download_url' => null,
    ]);

    $plainToken = 'query-token';
    ApiToken::create([
        'user_id' => $user->id,
        'name' => 'Stream Token',
        'token_hash' => ApiToken::hashToken($plainToken),
    ]);

    $response = $this->get('/api/integrations/meetings/' . $meeting->id . '/audio?api_token=' . $plainToken);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/ogg');
    expect($response->headers->get('Content-Disposition'))->toContain('stream.ogg');
    expect($response->getContent())->toBe('AUDIOBYTES');
});
