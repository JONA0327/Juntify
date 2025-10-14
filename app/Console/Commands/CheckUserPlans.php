<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CheckUserPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:check {--expired : Solo mostrar planes vencidos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisar el estado de los planes de todos los usuarios';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $onlyExpired = $this->option('expired');
        $now = Carbon::now();

        $this->info("📋 Estado de planes de usuarios - " . $now->format('Y-m-d H:i:s'));

        if ($onlyExpired) {
            $this->info("🔍 Mostrando solo planes vencidos");
        }

        // Obtener usuarios con información de planes
        $query = User::whereNotNull('plan_expires_at');

        if ($onlyExpired) {
            $query->where('plan_expires_at', '<', $now);
        }

        $users = $query->orderBy('plan_expires_at')->get();

        if ($users->count() === 0) {
            $this->info("✅ No hay usuarios " . ($onlyExpired ? "con planes vencidos" : "con fechas de expiración"));
            return;
        }

        $headers = ['Usuario', 'Email', 'Rol', 'Expira', 'Estado', 'Días'];
        $rows = [];

        foreach ($users as $user) {
            $daysToExpiry = $now->diffInDays($user->plan_expires_at, false);
            $isExpired = $user->isPlanExpired();
            $status = $isExpired ? '❌ Vencido' : '✅ Activo';

            if ($isExpired) {
                $daysText = "Hace " . abs($daysToExpiry) . " días";
            } else {
                $daysText = "En " . $daysToExpiry . " días";
            }

            $rows[] = [
                $user->full_name,
                $user->email,
                $user->roles,
                $user->plan_expires_at->format('Y-m-d'),
                $status,
                $daysText
            ];
        }

        $this->table($headers, $rows);

        // Resumen
        $totalUsers = $users->count();
        $expiredCount = $users->filter(fn($u) => $u->isPlanExpired())->count();
        $activeCount = $totalUsers - $expiredCount;

        $this->info("\n📊 Resumen:");
        $this->line("   Total usuarios: {$totalUsers}");
        $this->line("   Planes activos: {$activeCount}");
        $this->line("   Planes vencidos: {$expiredCount}");

        // Verificar usuarios sin plan_expires_at pero con roles de pago
        $usersWithoutExpiry = User::whereNull('plan_expires_at')
            ->whereNotIn('roles', ['free', 'developer', 'superadmin'])
            ->get();

        if ($usersWithoutExpiry->count() > 0) {
            $this->warn("\n⚠️  Usuarios con roles de pago pero sin fecha de expiración:");
            foreach ($usersWithoutExpiry as $user) {
                $this->line("   • {$user->full_name} ({$user->email}) - Rol: {$user->roles}");
            }
        }
    }
}
