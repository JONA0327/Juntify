<?php

use App\Models\User;
use App\Models\GoogleToken;
use App\Services\GoogleCalendarService;
use Google\Client;
use Mockery;

it('creates a calendar event', function () {
    $user = User::factory()->create(['username' => 'tester']);

    GoogleToken::create([
        'username'      => $user->username,
        'access_token'  => 'token',
        'refresh_token' => 'refresh',
        'expiry_date'   => now()->addHour(),
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken');
    $client->shouldReceive('isAccessTokenExpired')->andReturnFalse();

    $service = Mockery::mock(GoogleCalendarService::class);
    $service->shouldReceive('getClient')->andReturn($client);
    $service->shouldReceive('createEvent')
        ->once()
        ->with('Test', '2023-01-01T10:00:00Z', '2023-01-01T11:00:00Z', 'primary')
        ->andReturn('evt123');

    app()->instance(GoogleCalendarService::class, $service);

    $response = $this->actingAs($user)->post('/calendar/event', [
        'summary' => 'Test',
        'start'   => '2023-01-01T10:00:00Z',
        'end'     => '2023-01-01T11:00:00Z',
    ]);

    $response->assertOk()->assertJson(['id' => 'evt123']);
});
