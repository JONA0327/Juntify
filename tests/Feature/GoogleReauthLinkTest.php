<?php

use App\Models\User;

it('shows reauth link when google token is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertSee('/google/reauth');
});

