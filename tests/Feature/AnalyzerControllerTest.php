<?php

use App\Models\User;
use App\Models\Analyzer;

it('creates an analyzer', function () {
    $user = User::factory()->create();

    $data = [
        'id' => 'test-analyzer',
        'name' => 'Analyzer',
        'description' => 'desc',
        'system_prompt' => 'sys',
        'user_prompt_template' => 'tmpl',
        'userprotmp' => 'tmp2',
    ];

    $response = $this->actingAs($user)->post('/admin/analyzers', $data);

    $response->assertCreated()->assertJsonFragment(['id' => 'test-analyzer']);
    $this->assertDatabaseHas('analyzers', ['id' => 'test-analyzer']);
});

it('updates an analyzer', function () {
    $user = User::factory()->create();
    $analyzer = Analyzer::factory()->create(['name' => 'Old']);

    $response = $this->actingAs($user)
        ->put("/admin/analyzers/{$analyzer->id}", ['name' => 'New']);

    $response->assertOk()->assertJsonFragment(['name' => 'New']);
    $this->assertDatabaseHas('analyzers', ['id' => $analyzer->id, 'name' => 'New']);
});

it('deletes an analyzer', function () {
    $user = User::factory()->create();
    $analyzer = Analyzer::factory()->create();

    $response = $this->actingAs($user)->delete("/admin/analyzers/{$analyzer->id}");

    $response->assertOk()->assertJson(['deleted' => true]);
    $this->assertDatabaseMissing('analyzers', ['id' => $analyzer->id]);
});
