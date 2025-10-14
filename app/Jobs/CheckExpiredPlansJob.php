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
        $usersWithExpiredPlans = User::where('plan_expires_at', '<', now())
            ->where('roles', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->get();

        foreach ($usersWithExpiredPlans as $user) {
            // Cambiar rol a free
            $oldRole = $user->roles;
            $user->update(['roles' => 'free']);

            // Marcar suscripciones activas como canceladas
            $user->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status' => 'expired',
                    'cancelled_at' => now()
                ]);

            Log::info('User plan expired and role updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => 'free',
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

            if ($activeSubscriptions === 0 && $user->roles !== 'free') {
                $oldRole = $user->roles;
                $user->update([
                    'roles' => 'free',
                    'plan_expires_at' => null
                ]);

                Log::info('User role updated to free after subscription expired', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'old_role' => $oldRole,
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
