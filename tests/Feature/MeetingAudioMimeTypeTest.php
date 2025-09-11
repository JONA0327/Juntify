<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\TranscriptionLaravel;
use App\Models\GoogleToken;

uses(RefreshDatabase::class);

test('stream audio endpoint returns audio/mp4 for mp4 files', function () {
    $user = User::factory()->create(['username' => 'user']);

    GoogleToken::create([
        'username' => $user->username,
        'access_token' => json_encode([
            'access_token' => 'token',
            'expires_in' => 3600,
            'created' => time(),
        ]),
        'refresh_token' => 'refresh',
        'expiry_date' => now()->addDay(),
    ]);

    $meeting = TranscriptionLaravel::factory()->create([
        'username' => $user->username,
        'meeting_name' => 'Test Audio',
        'audio_drive_id' => null,
        'transcript_drive_id' => null,
    ]);

    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meeting->meeting_name);
    $fileName = $sanitized . '_' . $meeting->id . '.mp4';

    Storage::disk('public')->makeDirectory('temp');
    Storage::disk('public')->put('temp/' . $fileName, hex2bin('000000186674797069736f6d0000020069736f6d69736f32'));

    $response = $this->actingAs($user, 'sanctum')->get('/api/meetings/' . $meeting->id . '/audio');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/mp4');
});

