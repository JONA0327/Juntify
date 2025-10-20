<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GrantBasicPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plan:grant-basic {email} {days=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otorga plan basic a un usuario por un número específico de días';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $days = (int) $this->argument('days');

        $this->info("=== OTORGAR PLAN BASIC ===");
        $this->line("");

        // Buscar usuario
        $this->info("📧 Buscando usuario con email: {$email}");
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ Usuario no encontrado");
            return 1;
        }

        $this->info("✅ Usuario encontrado:");
        $this->line("   - ID: {$user->id}");
        $this->line("   - Username: {$user->username}");
        $this->line("   - Email: {$user->email}");
        $this->line("   - Plan actual: " . ($user->plan ?? 'No definido'));

        // Verificar si existen los campos de plan
        $this->line("");
        $this->info("📋 Verificando campos de plan...");

        try {
            $userTable = DB::select("DESCRIBE users");
            $hasPlans = false;
            $hasPlanExpires = false;

            foreach ($userTable as $column) {
                if ($column->Field === 'plan') {
                    $hasPlans = true;
                }
                if ($column->Field === 'plan_expires_at') {
                    $hasPlanExpires = true;
                }
            }

            // Agregar campos si no existen
            if (!$hasPlans) {
                $this->line("⚙️  Agregando campo 'plan' a la tabla users...");
                DB::statement("ALTER TABLE users ADD COLUMN plan VARCHAR(50) DEFAULT 'free'");
                $this->info("✅ Campo 'plan' agregado");
            }

            if (!$hasPlanExpires) {
                $this->line("⚙️  Agregando campo 'plan_expires_at' a la tabla users...");
                DB::statement("ALTER TABLE users ADD COLUMN plan_expires_at TIMESTAMP NULL");
                $this->info("✅ Campo 'plan_expires_at' agregado");
            }

            // Verificar si existe plan_code
            $hasPlanCode = false;
            foreach ($userTable as $column) {
                if ($column->Field === 'plan_code') {
                    $hasPlanCode = true;
                    break;
                }
            }

            if (!$hasPlanCode) {
                $this->line("⚙️  Agregando campo 'plan_code' a la tabla users...");
                DB::statement("ALTER TABLE users ADD COLUMN plan_code VARCHAR(50) DEFAULT 'free'");
                $this->info("✅ Campo 'plan_code' agregado");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error verificando/creando campos: " . $e->getMessage());
            return 1;
        }

        // Otorgar plan basic
        $expirationDate = Carbon::now()->addDays($days);

        $this->line("");
        $this->info("🎯 Otorgando plan basic por {$days} días...");
        $this->line("   - Fecha de expiración: {$expirationDate->format('Y-m-d H:i:s')}");

        try {
            // Forzar actualización directa de todos los campos relacionados con plan
            $user->plan = 'basic';
            $user->roles = 'basic'; // También actualizar roles
            $user->plan_code = 'basic'; // También actualizar plan_code
            $user->plan_expires_at = $expirationDate;
            $user->save();

            // Verificar que se actualizó
            $user->refresh();            $this->line("");
            $this->info("✅ Plan basic otorgado exitosamente");
            $this->line("   - Usuario: {$user->username} ({$user->email})");
            $this->line("   - Plan: basic");
            $this->line("   - Expira: {$expirationDate->format('Y-m-d H:i:s')}");
            $this->line("   - Días: {$days}");

            $this->line("");
            $this->info("🎉 ¡Proceso completado exitosamente!");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error al otorgar plan: " . $e->getMessage());
            return 1;
        }
    }
}
