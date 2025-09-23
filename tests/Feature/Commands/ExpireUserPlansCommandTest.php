<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExpireUserPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_degrades_users_after_grace_period(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 5, 10, 10));

        $user = User::factory()->create([
            'roles' => 'basic',
            'plan_expires_at' => Carbon::now()->subDays(6),
        ]);

        $plan = UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => 'basic-monthly',
            'role' => 'basic',
            'starts_at' => Carbon::now()->subMonth(),
            'expires_at' => Carbon::now()->subDays(6),
            'status' => UserPlan::STATUS_ACTIVE,
            'has_unlimited_roles' => false,
        ]);

        Log::spy();

        Artisan::call('plans:expire');

        $user->refresh();
        $plan->refresh();

        $this->assertSame('free', $user->roles);
        $this->assertNull($user->plan_expires_at);
        $this->assertSame(UserPlan::STATUS_EXPIRED, $plan->status);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($user) {
            return str_contains($message, 'Plan expirado')
                && ($context['user_id'] ?? null) === $user->id;
        });

        Carbon::setTestNow();
    }
}
