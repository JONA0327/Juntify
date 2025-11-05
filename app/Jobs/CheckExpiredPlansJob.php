<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

class CheckExpiredPlansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting expired plans check job');

        $expiredCount = 0;

        // Buscar usuarios con planes expirados que no tengan rol 'free'
        // Excluir roles protegidos que no deben expirar: BNI, developer, founder, superadmin
        // Usar comparaciones case-insensitive para evitar que variaciones de mayúsculas provoquen degradación
        $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];
        $protectedListSql = "('" . implode("','", $protectedRoles) . "')";

        $usersWithExpiredPlans = User::where('plan_expires_at', '<', now())
            ->where(function ($q) {
                $q->whereNull('is_role_protected')
                  ->orWhere('is_role_protected', false);
            })
            ->whereRaw("LOWER(roles) != 'free'")
            ->whereRaw("LOWER(roles) NOT IN $protectedListSql")
            ->whereRaw("LOWER(plan) NOT IN $protectedListSql")  // También proteger por campo 'plan'
            ->whereNotNull('plan_expires_at')
            ->get();

        foreach ($usersWithExpiredPlans as $user) {
            // Cambiar todas las columnas relacionadas con el plan
            $oldRole = $user->roles;
            $oldPlan = $user->plan;
            $oldPlanCode = $user->plan_code;

            $user->update([
                'roles' => 'free',
                'plan' => 'free',
                'plan_code' => 'free'
            ]);

            // Marcar suscripciones activas como canceladas
            $user->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status' => 'expired',
                    'cancelled_at' => now()
                ]);

            Log::info('User plan expired and all fields updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_role' => $oldRole,
                'old_plan' => $oldPlan,
                'old_plan_code' => $oldPlanCode,
                'new_role' => 'free',
                'new_plan' => 'free',
                'new_plan_code' => 'free',
                'expired_at' => $user->plan_expires_at
            ]);

            $expiredCount++;
        }

        // También verificar suscripciones que expiraron pero el usuario aún no se actualizó
        $expiredSubscriptions = UserSubscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->with('user')
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $user = $subscription->user;

            // Marcar suscripción como expirada
            $subscription->update([
                'status' => 'expired',
                'cancelled_at' => now()
            ]);

            // Si el usuario no tiene otras suscripciones activas, cambiar a free
            $activeSubscriptions = $user->subscriptions()->where('status', 'active')->count();

            // Proteger roles que no deben expirar: BNI, developer, founder, superadmin
            $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];

            // Si el usuario tiene la bandera is_role_protected activada, no lo degradamos
            if ($activeSubscriptions === 0 && (empty($user->is_role_protected) || $user->is_role_protected === false) && strtolower($user->roles) !== 'free' && !in_array(strtolower($user->roles), $protectedRoles) && !in_array(strtolower($user->plan), $protectedRoles)) {
                $oldRole = $user->roles;
                $oldPlan = $user->plan;
                $oldPlanCode = $user->plan_code;

                $user->update([
                    'roles' => 'free',
                    'plan' => 'free',
                    'plan_code' => 'free',
                    'plan_expires_at' => null
                ]);

                Log::info('User role updated to free after subscription expired', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'old_role' => $oldRole,
                    'old_plan' => $oldPlan,
                    'old_plan_code' => $oldPlanCode,
                    'new_role' => 'free',
                    'new_plan' => 'free',
                    'new_plan_code' => 'free',
                    'subscription_id' => $subscription->id
                ]);

                $expiredCount++;
            }
        }

        Log::info('Expired plans check job completed', [
            'users_updated' => $expiredCount,
            'expired_subscriptions' => $expiredSubscriptions->count()
        ]);
    }
}
