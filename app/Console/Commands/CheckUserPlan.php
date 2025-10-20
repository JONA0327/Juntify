<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CheckUserPlan extends Command
{
    protected $signature = 'plan:check {email}';
    protected $description = 'Verificar el plan de un usuario';

    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado");
            return 1;
        }

        $this->info("=== INFORMACIÓN DEL USUARIO ===");
        $this->line("Username: {$user->username}");
        $this->line("Email: {$user->email}");
        $this->line("Plan: " . ($user->plan ?? 'No definido'));
        $this->line("Expira: " . ($user->plan_expires_at ?? 'No definido'));

        if ($user->plan_expires_at) {
            $expiresAt = Carbon::parse($user->plan_expires_at);
            $now = Carbon::now();

            if ($expiresAt->isFuture()) {
                $daysRemaining = $now->diffInDays($expiresAt);
                $hoursRemaining = $now->diffInHours($expiresAt) % 24;
                $this->info("Estado: ✅ ACTIVO ({$daysRemaining} días y {$hoursRemaining} horas restantes)");
            } else {
                $this->warn("Estado: ⚠️  EXPIRADO");
            }
        }

        return 0;
    }
}
