<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class DebugUserPlan extends Command
{
    protected $signature = 'plan:debug {email}';
    protected $description = 'Debuggear el plan de un usuario';

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("=== DEBUG DEL PLAN DE USUARIO ===");
        $this->line("");

        // Verificar directamente en la base de datos
        $this->info("📊 CONSULTA DIRECTA A LA BASE DE DATOS:");
        $userDb = DB::table('users')->where('email', $email)->first();

        if (!$userDb) {
            $this->error("Usuario no encontrado en la base de datos");
            return 1;
        }

        $this->line("   - ID: {$userDb->id}");
        $this->line("   - Username: {$userDb->username}");
        $this->line("   - Email: {$userDb->email}");
        $this->line("   - Plan (DB): " . ($userDb->plan ?? 'NULL'));
        $this->line("   - Plan expires at (DB): " . ($userDb->plan_expires_at ?? 'NULL'));

        // Verificar con Eloquent
        $this->line("");
        $this->info("🔍 CONSULTA CON ELOQUENT:");
        $userModel = User::where('email', $email)->first();

        if ($userModel) {
            $this->line("   - Plan (Model): " . ($userModel->plan ?? 'NULL'));
            $this->line("   - Plan expires at (Model): " . ($userModel->plan_expires_at ?? 'NULL'));
        }

        // Verificar la estructura de la tabla
        $this->line("");
        $this->info("📋 ESTRUCTURA DE LA TABLA USERS:");
        $columns = DB::select("DESCRIBE users");
        foreach ($columns as $column) {
            if (strpos(strtolower($column->Field), 'plan') !== false) {
                $this->line("   - {$column->Field}: {$column->Type} (Default: {$column->Default})");
            }
        }

        // Verificar si hay otros campos relacionados con plan
        $this->line("");
        $this->info("🔎 OTROS CAMPOS RELACIONADOS CON PLAN:");
        foreach ($columns as $column) {
            if (strpos(strtolower($column->Field), 'subscription') !== false ||
                strpos(strtolower($column->Field), 'tier') !== false ||
                strpos(strtolower($column->Field), 'role') !== false ||
                strpos(strtolower($column->Field), 'plan_code') !== false) {
                $value = $userDb->{$column->Field} ?? 'NULL';
                $this->line("   - {$column->Field}: {$value}");
            }
        }

        return 0;
    }
}
