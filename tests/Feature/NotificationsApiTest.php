<?php

use App\Models\Notification;
use App\Models\User;

it('returns notifications for authenticated session user', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    Notification::create([
        'remitente' => $sender->id,
        'emisor' => $user->id,
        'status' => 'pending',
        'message' => 'hi',
        'type' => 'generic',
        'data' => json_encode([]),
    ]);

    $response = $this->actingAs($user)->getJson('/api/notifications');

    $response->assertOk()
        ->assertJsonFragment(['message' => 'hi']);
});
