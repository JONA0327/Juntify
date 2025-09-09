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

it('accepts a contact request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $notification = Notification::create([
        'remitente' => $sender->id,
        'emisor' => $receiver->id,
        'type' => 'contact_request',
        'message' => 'Solicitud de contacto',
        'status' => 'pending',
        'data' => [],
    ]);

    $response = $this->actingAs($receiver)->postJson("/api/contacts/requests/{$notification->id}/respond", [
        'action' => 'accept',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Solicitud aceptada',
    ]);

    expect(Contact::where('user_id', $receiver->id)
        ->where('contact_id', $sender->id)
        ->exists())->toBeTrue();
    expect(Contact::where('user_id', $sender->id)
        ->where('contact_id', $receiver->id)
        ->exists())->toBeTrue();
    expect(Notification::find($notification->id))->toBeNull();
});

it('rejects a contact request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $notification = Notification::create([
        'remitente' => $sender->id,
        'emisor' => $receiver->id,
        'type' => 'contact_request',
        'message' => 'Solicitud de contacto',
        'status' => 'pending',
        'data' => [],
    ]);

    $response = $this->actingAs($receiver)->postJson("/api/contacts/requests/{$notification->id}/respond", [
        'action' => 'reject',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Solicitud rechazada',
    ]);

    expect(Contact::where('user_id', $receiver->id)
        ->where('contact_id', $sender->id)
        ->exists())->toBeFalse();
    expect(Contact::where('user_id', $sender->id)
        ->where('contact_id', $receiver->id)
        ->exists())->toBeFalse();
    expect(Notification::find($notification->id))->toBeNull();
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
