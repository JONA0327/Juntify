<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows integrations login for users with argon hashed passwords', function () {
    $password = 'SuperSecure123!';

    $user = User::factory()->create([
        'password' => Hash::driver('argon')->make($password),
    ]);

    $response = $this->postJson('/api/integrations/login', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Integrations Test Device',
    ]);

    $response->assertCreated();

    $response->assertJsonStructure([
        'token',
        'token_type',
        'abilities',
        'created_at',
        'user' => [
            'id',
            'username',
            'full_name',
            'email',
            'role',
        ],
    ]);

    $this->assertDatabaseHas('api_tokens', [
        'user_id' => $user->id,
        'name' => 'Integrations Test Device',
    ]);
});

it('allows integrations login using the login field with an email', function () {
    $password = 'ValidPassword123';

    $user = User::factory()->create([
        'password' => Hash::make($password),
    ]);

    $response = $this->postJson('/api/integrations/login', [
        'login' => $user->email,
        'password' => $password,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('user.email', $user->email);
});

it('allows integrations login using the login field with a username', function () {
    $password = 'AnotherPassword456';

    $user = User::factory()->create([
        'password' => Hash::make($password),
    ]);

    $response = $this->postJson('/api/integrations/login', [
        'login' => $user->username,
        'password' => $password,
        'device_name' => 'Username Device',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('user.username', $user->username);

    $this->assertDatabaseHas('api_tokens', [
        'user_id' => $user->id,
        'name' => 'Username Device',
    ]);
});
