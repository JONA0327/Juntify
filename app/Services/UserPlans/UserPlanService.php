<?php

namespace App\Services\UserPlans;

use App\Models\PlanPurchase;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserPlanService
{
    public const GRACE_DAYS = 5;

    public function activateFromPayment(User $user, array $metadata, array $paymentData): UserPlan
    {
        return DB::transaction(function () use ($user, $metadata, $paymentData) {
            $planId = (string) (data_get($metadata, 'plan_id')
                ?? data_get($metadata, 'plan_name')
                ?? data_get($metadata, 'role')
                ?? $user->roles
                ?? 'free');

            $role = (string) (data_get($metadata, 'role') ?? $user->roles ?? 'free');
            $startsAt = Carbon::parse(
                data_get($metadata, 'starts_at')
                ?? data_get($paymentData, 'paid_at')
                ?? Carbon::now()
            );

            $billingCycle = $this->resolveCycle($metadata);
            $hasUnlimitedRoles = $this->hasUnlimitedRoles($role, $metadata);
            $expiresAt = $hasUnlimitedRoles ? null : $this->calculateExpiration($startsAt, $billingCycle);

            $user->plans()
                ->active()
                ->get()
                ->each(function (UserPlan $plan) use ($startsAt) {
                    $plan->status = UserPlan::STATUS_EXPIRED;
                    if ($plan->expires_at === null || $plan->expires_at->greaterThan($startsAt)) {
                        $plan->expires_at = $startsAt;
                    }
                    $plan->save();
                });

            $userPlan = UserPlan::create([
                'user_id' => $user->id,
                'plan_id' => $planId,
                'role' => $role,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => UserPlan::STATUS_ACTIVE,
                'has_unlimited_roles' => $hasUnlimitedRoles,
            ]);

            $paidAt = data_get($paymentData, 'paid_at')
                ? Carbon::parse($paymentData['paid_at'])
                : $startsAt;

            $purchasePayload = [
                'user_id' => $user->id,
                'user_plan_id' => $userPlan->id,
                'provider' => $paymentData['provider'] ?? 'mercado_pago',
                'payment_id' => $paymentData['payment_id'] ?? null,
                'external_reference' => $paymentData['external_reference'] ?? null,
                'status' => $paymentData['status'] ?? 'approved',
                'amount' => $paymentData['amount'] ?? null,
                'currency' => $paymentData['currency'] ?? null,
                'paid_at' => $paidAt,
                'metadata' => $paymentData['metadata'] ?? $metadata,
            ];

            if ($purchasePayload['payment_id']) {
                PlanPurchase::updateOrCreate(
                    [
                        'provider' => $purchasePayload['provider'],
                        'payment_id' => $purchasePayload['payment_id'],
                    ],
                    $purchasePayload
                );
            } else {
                PlanPurchase::create($purchasePayload);
            }

            $user->roles = $role;
            $user->plan_expires_at = $expiresAt;
            $user->save();

            return $userPlan;
        });
    }

    public function calculateExpiration(Carbon $startsAt, string $cycle): Carbon
    {
        return match ($cycle) {
            'year', 'yearly', 'annual', 'anual', 'annually' => $startsAt->copy()->addYear(),
            default => $startsAt->copy()->addMonthNoOverflow(),
        };
    }

    public function resolveCycle(array $metadata): string
    {
        $raw = strtolower((string) (data_get($metadata, 'billing_cycle')
            ?? data_get($metadata, 'cycle')
            ?? data_get($metadata, 'frequency')
            ?? data_get($metadata, 'interval')
            ?? ''));

        if ($raw !== '') {
            if (
                str_contains($raw, 'year')
                || str_contains($raw, 'anio')
                || str_contains($raw, 'año')
                || str_contains($raw, 'anual')
                || str_contains($raw, '12')
            ) {
                return 'yearly';
            }

            return 'monthly';
        }

        $durationInMonths = (int) (data_get($metadata, 'duration_in_months')
            ?? data_get($metadata, 'months')
            ?? data_get($metadata, 'interval_count')
            ?? 1);

        return $durationInMonths >= 12 ? 'yearly' : 'monthly';
    }

    public function hasUnlimitedRoles(?string $role, array $metadata = []): bool
    {
        if (filter_var(data_get($metadata, 'unlimited_roles'), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if (filter_var(data_get($metadata, 'has_unlimited_roles'), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $role = $role ? strtolower($role) : null;

        return $role !== null && in_array($role, ['founder', 'developer', 'superadmin'], true);
    }

    public function downgradeExpiredPlans(?Carbon $now = null): int
    {
        $now = $now ? $now->copy() : Carbon::now();
        $graceLimit = $now->copy()->subDays(self::GRACE_DAYS);

        $plans = UserPlan::query()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->with('user')
            ->get();

        $processed = 0;

        foreach ($plans as $plan) {
            DB::transaction(function () use ($plan, $graceLimit, &$processed) {
                $plan->status = UserPlan::STATUS_EXPIRED;
                $plan->save();

                $user = $plan->user;

                if (! $user) {
                    return;
                }

                if ($plan->expires_at && $plan->expires_at->lessThanOrEqualTo($graceLimit)) {
                    if ($user->roles !== 'free') {
                        $user->roles = 'free';
                    }
                    $user->plan_expires_at = null;
                    $user->save();

                    Log::info('Plan expirado. Usuario degradado a free.', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->plan_id,
                        'expired_at' => $plan->expires_at,
                    ]);
                } else {
                    $user->plan_expires_at = $plan->expires_at;
                    $user->save();
                }

                $processed++;
            });
        }

        return $processed;
    }
}
