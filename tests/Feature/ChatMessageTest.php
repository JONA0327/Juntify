<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Models\User;
use App\Models\Chat;

uses(RefreshDatabase::class);

it('sends messages with attachments', function () {
    Storage::fake('local');

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $chat = Chat::create([
        'user_one_id' => $user1->id,
        'user_two_id' => $user2->id,
    ]);

    $this->actingAs($user1, 'sanctum');

    $response = $this->postJson("/api/chats/{$chat->id}/messages", [
        'body' => 'hola',
        'file' => UploadedFile::fake()->create('test.txt', 1),
        'voice' => UploadedFile::fake()->create('voice.webm', 1),
    ]);

    $response->assertOk();
    $message = $response->json();
    expect($message['body'])->toBe('hola');
    Storage::disk('local')->assertExists($message['file_path']);
    Storage::disk('local')->assertExists($message['voice_path']);

    $list = $this->getJson("/api/chats/{$chat->id}");
    $list->assertOk()->assertJsonFragment(['body' => 'hola']);
});
