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

            $role = (string) (data_get($metadata, 'role')
                ?? $this->mapPlanCodeToRole(data_get($metadata, 'plan_code'))
                ?? $user->roles
                ?? 'free');
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

            // Actualizar usuario con el nuevo plan y rol
            $user->roles = $role;
            $user->plan_expires_at = $expiresAt;

            // Actualizar también las columnas plan y plan_code
            $planCode = data_get($metadata, 'plan_code') ?? $this->mapRoleToPlanCode($role);
            $user->plan = $this->mapRoleToPlan($role);
            $user->plan_code = $planCode;

            $user->save();

            Log::info('User plan activated', [
                'user_id' => $user->id,
                'role' => $role,
                'plan' => $user->plan,
                'plan_code' => $user->plan_code,
                'expires_at' => $expiresAt?->toDateTimeString()
            ]);

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

        // Incluir BNI en roles con permisos ilimitados
        return $role !== null && in_array($role, ['founder', 'developer', 'superadmin', 'bni'], true);
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
                    // No degradar usuarios protegidos por bandera o roles especiales
                    $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];
                    if (!empty($user->is_role_protected) || in_array(strtolower($user->roles ?? ''), $protectedRoles, true) || in_array(strtolower($user->plan ?? ''), $protectedRoles, true)) {
                        // Si ya es free, limpiar expiry; si no, preservar rol
                        $user->plan_expires_at = null;
                        $user->save();
                    } else {
                        if ($user->roles !== 'free') {
                            $oldRole = $user->roles;
                            $oldPlan = $user->plan;
                            $oldPlanCode = $user->plan_code;

                            // Actualizar todas las columnas relacionadas con el plan
                            $user->roles = 'free';
                            $user->plan = 'free';
                            $user->plan_code = 'free';
                            $user->plan_expires_at = null;
                            $user->save();

                            Log::info('Plan expirado. Usuario degradado completamente a free.', [
                                'user_id' => $user->id,
                                'plan_id' => $plan->plan_id,
                                'old_role' => $oldRole,
                                'old_plan' => $oldPlan,
                                'old_plan_code' => $oldPlanCode,
                                'new_role' => 'free',
                                'new_plan' => 'free',
                                'new_plan_code' => 'free',
                                'expired_at' => $plan->expires_at,
                            ]);
                        } else {
                            $user->plan_expires_at = null;
                            $user->save();
                        }
                    }
                } else {
                    $user->plan_expires_at = $plan->expires_at;
                    $user->save();
                }

                $processed++;
            });
        }

        return $processed;
    }

    /**
     * Mapea el código del plan al rol del usuario
     */
    private function mapPlanCodeToRole(?string $planCode): ?string
    {
        if (!$planCode) return null;

        return match($planCode) {
            'free', 'gratis' => 'free',
            'basic', 'basico' => 'basic', // Acepta ambos pero normaliza a basic
            'business', 'negocios' => 'business', // Acepta ambos pero normaliza a business
            'enterprise', 'empresas' => 'enterprise', // Acepta ambos pero normaliza a enterprise
            // Roles especiales mantienen su mismo código
            'developer' => 'developer',
            'bni' => 'bni',
            'founder' => 'founder',
            'superadmin' => 'superadmin',
            default => $planCode // fallback al código del plan
        };
    }

    /**
     * Mapea el rol del usuario al código del plan
     */
    private function mapRoleToPlanCode(string $role): string
    {
        return match($role) {
            'free' => 'free',
            'bni' => 'bni',
            'basic' => 'basic',
            'business' => 'business',
            'enterprise' => 'enterprise',
            // Roles especiales mantienen su código
            'developer' => 'developer',
            'founder' => 'founder',
            'superadmin' => 'superadmin',
            default => $role // fallback al rol
        };
    }

    /**
     * Mapea el rol del usuario al nombre del plan
     */
    private function mapRoleToPlan(string $role): string
    {
        return match($role) {
            'free' => 'free',
            'bni' => 'bni',
            'basic' => 'basic',
            'business' => 'basic', // Los usuarios business también tienen plan basic
            'enterprise' => 'basic', // Los usuarios enterprise también tienen plan basic
            // Roles especiales - su plan es igual al rol (no necesitan comprar)
            'developer' => 'developer',
            'founder' => 'founder',
            'superadmin' => 'superadmin',
            default => 'free' // fallback a free
        };
    }
}
