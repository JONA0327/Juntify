<?php

use App\Models\OrganizationActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('removes activities from previous months', function () {
    $now = now();

    OrganizationActivity::create([
        'action' => 'old1',
        'created_at' => $now->copy()->subMonths(2),
        'updated_at' => $now->copy()->subMonths(2),
    ]);

    OrganizationActivity::create([
        'action' => 'old2',
        'created_at' => $now->copy()->subMonth(),
        'updated_at' => $now->copy()->subMonth(),
    ]);

    $current = OrganizationActivity::create([
        'action' => 'current',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan('activities:cleanup')->assertExitCode(0);

    $activities = OrganizationActivity::all();

    expect($activities)->toHaveCount(1);
    expect($activities->first()->id)->toBe($current->id);
});
