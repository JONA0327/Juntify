<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateExpiredPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:update-expired {--dry-run : Solo mostrar qué se haría sin hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar automáticamente usuarios con planes vencidos a rol free y dar 1 año a founders';

    /**
     * Roles protegidos que nunca deben ser degradados
     */
    protected $protectedRoles = ['developer', 'superadmin'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now();

        $this->info("🔄 Iniciando verificación de planes vencidos...");
        if ($dryRun) {
            $this->warn("⚠️  Modo DRY RUN - No se harán cambios reales");
        }

        // 1. Otorgar 1 año a usuarios founder desde hoy
        $this->grantFounderYear($dryRun, $now);

        // 2. Otorgar 1 mes a usuarios enterprise desde hoy
        $this->grantEnterpriseMonth($dryRun, $now);

        // 3. Degradar usuarios con planes vencidos (excepto protegidos)
        $this->downgradeExpiredUsers($dryRun, $now);

        $this->info("✅ Proceso completado");
    }

    /**
     * Otorgar 1 año gratis a usuarios founder desde hoy
     */
    private function grantFounderYear($dryRun, $now)
    {
        $this->info("\n📅 Procesando usuarios founder...");

        $founders = User::where('roles', 'founder')
            ->where(function($query) use ($now) {
                $query->whereNull('plan_expires_at')
                      ->orWhere('plan_expires_at', '<', $now);
            })
            ->get();

        if ($founders->count() === 0) {
            $this->info("   No hay founders que necesiten actualización");
            return;
        }

        $newExpiryDate = $now->copy()->addYear();

        foreach ($founders as $founder) {
            $this->line("   👑 {$founder->full_name} ({$founder->email})");
            $this->line("      Anterior: " . ($founder->plan_expires_at ? $founder->plan_expires_at->format('Y-m-d') : 'Sin fecha'));
            $this->line("      Nueva: {$newExpiryDate->format('Y-m-d')}");

            if (!$dryRun) {
                $founder->plan_expires_at = $newExpiryDate;
                $founder->save();

                Log::info("Founder plan extended", [
                    'user_id' => $founder->id,
                    'email' => $founder->email,
                    'new_expiry' => $newExpiryDate->toDateString()
                ]);
            }
        }

        $this->info("   📊 Total founders procesados: {$founders->count()}");
    }

    /**
     * Otorgar 1 mes gratis a usuarios enterprise desde hoy
     */
    private function grantEnterpriseMonth($dryRun, $now)
    {
        $this->info("\n🏢 Procesando usuarios enterprise...");

        $enterprises = User::where('roles', 'enterprise')
            ->where(function($query) use ($now) {
                $query->whereNull('plan_expires_at')
                      ->orWhere('plan_expires_at', '<', $now);
            })
            ->get();

        if ($enterprises->count() === 0) {
            $this->info("   No hay usuarios enterprise que necesiten actualización");
            return;
        }

        $newExpiryDate = $now->copy()->addMonth();

        foreach ($enterprises as $enterprise) {
            $this->line("   🏢 {$enterprise->full_name} ({$enterprise->email})");
            $this->line("      Anterior: " . ($enterprise->plan_expires_at ? $enterprise->plan_expires_at->format('Y-m-d') : 'Sin fecha'));
            $this->line("      Nueva: {$newExpiryDate->format('Y-m-d')}");

            if (!$dryRun) {
                $enterprise->plan_expires_at = $newExpiryDate;
                $enterprise->save();

                Log::info("Enterprise plan extended", [
                    'user_id' => $enterprise->id,
                    'email' => $enterprise->email,
                    'new_expiry' => $newExpiryDate->toDateString()
                ]);
            }
        }

        $this->info("   📊 Total enterprises procesados: {$enterprises->count()}");
    }

    /**
     * Degradar usuarios con planes vencidos a rol free
     */
    private function downgradeExpiredUsers($dryRun, $now)
    {
        $this->info("\n⏰ Procesando planes vencidos...");

        // Usuarios con fecha de expiración vencida
        $expiredUsers = User::where('plan_expires_at', '<', $now)
            ->whereNotIn('roles', array_merge($this->protectedRoles, ['free']))
            ->get();

        // Usuarios con roles de pago pero sin fecha de expiración (excepto founder)
        $usersWithoutExpiry = User::whereNull('plan_expires_at')
            ->whereNotIn('roles', array_merge($this->protectedRoles, ['free', 'founder']))
            ->get();

        $allUsersToDowngrade = $expiredUsers->merge($usersWithoutExpiry);

        if ($allUsersToDowngrade->count() === 0) {
            $this->info("   No hay usuarios con planes vencidos que procesar");
            return;
        }

        foreach ($allUsersToDowngrade as $user) {
            $this->line("   ⬇️  {$user->full_name} ({$user->email})");
            $this->line("      Rol actual: {$user->roles}");

            if ($user->plan_expires_at) {
                $this->line("      Venció: {$user->plan_expires_at->format('Y-m-d')}");
            } else {
                $this->line("      Sin fecha de expiración (rol de pago sin plan válido)");
            }

            $this->line("      Nuevo rol: free");

            if (!$dryRun) {
                $oldRole = $user->roles;
                $user->roles = 'free';
                $user->save();

                Log::info("User downgraded to free", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'old_role' => $oldRole,
                    'expired_at' => $user->plan_expires_at ? $user->plan_expires_at->toDateString() : 'No expiry date',
                    'reason' => $user->plan_expires_at ? 'expired' : 'no_expiry_date'
                ]);
            }
        }

        $this->info("   📊 Total usuarios degradados: {$allUsersToDowngrade->count()}");
    }
}
