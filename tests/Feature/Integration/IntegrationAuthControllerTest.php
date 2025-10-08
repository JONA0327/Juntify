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
