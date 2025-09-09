<?php

use App\Models\Contact;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

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

it('deletes a contact and its inverse', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $contact = Contact::create([
        'id' => (string) Str::uuid(),
        'user_id' => $userA->id,
        'contact_id' => $userB->id,
    ]);

    Contact::create([
        'id' => (string) Str::uuid(),
        'user_id' => $userB->id,
        'contact_id' => $userA->id,
    ]);

    $response = $this->actingAs($userA)->deleteJson("/api/contacts/{$contact->id}");

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Contacto eliminado',
    ]);

    expect(Contact::where('user_id', $userA->id)
        ->where('contact_id', $userB->id)
        ->exists())->toBeFalse();
    expect(Contact::where('user_id', $userB->id)
        ->where('contact_id', $userA->id)
        ->exists())->toBeFalse();
});
