<?php

use App\Models\PendingRecording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns status of a pending recording', function () {
    $user = User::factory()->create();

    $recording = PendingRecording::create([
        'user_id' => $user->id,
        'meeting_name' => 'Test',
        'audio_drive_id' => 'drive123',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/pending-recordings/{$recording->id}");

    $response->assertOk()->assertJson([
        'id' => $recording->id,
        'status' => PendingRecording::STATUS_PENDING,
    ]);
});
