<?php

use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;

it('sends contact request by username', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $response = $this->actingAs($sender)->postJson('/api/contacts', [
        'username' => $receiver->username,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Solicitud enviada',
        ]);

    expect(Notification::where('remitente', $sender->id)
        ->where('emisor', $receiver->id)
        ->where('type', 'contact_request')
        ->exists())->toBeTrue();

    expect(Contact::where('user_id', $sender->id)
        ->where('contact_id', $receiver->id)
        ->exists())->toBeFalse();
});

it('sends contact request by email', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $response = $this->actingAs($sender)->postJson('/api/contacts', [
        'email' => $receiver->email,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Solicitud enviada',
        ]);

    expect(Notification::where('remitente', $sender->id)
        ->where('emisor', $receiver->id)
        ->where('type', 'contact_request')
        ->exists())->toBeTrue();

    expect(Contact::where('user_id', $sender->id)
        ->where('contact_id', $receiver->id)
        ->exists())->toBeFalse();
});
