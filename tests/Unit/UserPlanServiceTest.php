<?php

namespace Tests\Unit;

use App\Models\PlanPurchase;
use App\Models\User;
use App\Services\UserPlans\UserPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_expiration_returns_full_month_and_year(): void
    {
        $service = new UserPlanService();
        $start = Carbon::create(2024, 1, 31, 12, 0, 0);

        $monthly = $service->calculateExpiration($start, 'monthly');
        $yearly = $service->calculateExpiration($start, 'yearly');

        $this->assertSame('2024-02-29 12:00:00', $monthly->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-31 12:00:00', $yearly->format('Y-m-d H:i:s'));
    }

    public function test_activate_from_payment_persists_purchase_and_updates_user(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 5, 10, 10, 0, 0));

        $user = User::factory()->create([
            'roles' => 'free',
        ]);

        $metadata = [
            'plan_id' => 'basic-monthly',
            'role' => 'basic',
            'billing_cycle' => 'monthly',
            'user_id' => $user->id,
        ];

        $paymentData = [
            'provider' => 'mercado_pago',
            'payment_id' => 'pay-123',
            'external_reference' => 'ext-123',
            'status' => 'approved',
            'amount' => 199.90,
            'currency' => 'ARS',
            'paid_at' => Carbon::now()->toIso8601String(),
            'metadata' => $metadata,
        ];

        $service = app(UserPlanService::class);
        $plan = $service->activateFromPayment($user, $metadata, $paymentData);

        $user->refresh();

        $this->assertSame('basic-monthly', $plan->plan_id);
        $this->assertSame('basic', $user->roles);
        $this->assertNotNull($user->plan_expires_at);
        $this->assertSame(
            Carbon::now()->addMonthNoOverflow()->format('Y-m-d H:i:s'),
            $user->plan_expires_at->format('Y-m-d H:i:s')
        );

        $this->assertDatabaseHas('plan_purchases', [
            'user_id' => $user->id,
            'user_plan_id' => $plan->id,
            'provider' => 'mercado_pago',
            'payment_id' => 'pay-123',
            'status' => 'approved',
        ]);

        $purchase = PlanPurchase::where('payment_id', 'pay-123')->first();
        $this->assertNotNull($purchase);
        $this->assertSame('ext-123', $purchase->external_reference);

        Carbon::setTestNow();
    }
}
