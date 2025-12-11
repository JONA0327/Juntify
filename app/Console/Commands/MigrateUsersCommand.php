<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class MigrateUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:users
                            {--dry-run : Solo mostrar qué usuarios se migrarían}
                            {--generate-uuids : Generar nuevos UUIDs para usuarios}
                            {--default-password=password : Password por defecto para usuarios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra usuarios de la BD antigua con transformaciones específicas (IDs a UUIDs, passwords, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("👥 Iniciando migración específica de usuarios");

        $dryRun = $this->option('dry-run');
        $generateUuids = $this->option('generate-uuids');
        $defaultPassword = $this->option('default-password');

        try {
            // Verificar conexiones
            $this->checkConnections();

            // Obtener usuarios de BD antigua
            $oldUsers = DB::connection('juntify_old_local')->table('users')->get();
            $totalUsers = $oldUsers->count();

            $this->info("📊 Usuarios encontrados en BD antigua: $totalUsers");

            if ($dryRun) {
                $this->showDryRunResults($oldUsers);
                return 0;
            }

            // Confirmar migración
            if (!$this->confirm("¿Proceder con la migración de $totalUsers usuarios?")) {
                $this->info("Migración cancelada por el usuario");
                return 0;
            }

            $this->migrateUsers($oldUsers, $generateUuids, $defaultPassword);

            $this->info("✅ Migración de usuarios completada exitosamente!");

        } catch (Exception $e) {
            $this->error("❌ Error durante la migración: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Verificar conexiones de base de datos
     */
    protected function checkConnections(): void
    {
        DB::connection('juntify_old_local')->getPdo();
        DB::connection('mysql')->getPdo();
        $this->info("✅ Conexiones de BD verificadas");
    }

    /**
     * Mostrar resultados de dry-run
     */
    protected function showDryRunResults($oldUsers): void
    {
        $this->info("🔍 [DRY-RUN] Usuarios que se migrarían:");

        $table = [];
        foreach ($oldUsers->take(10) as $user) {
            $newId = property_exists($user, 'id') ? Str::uuid()->toString() : 'N/A';
            $table[] = [
                'ID Antiguo' => $user->id ?? 'N/A',
                'Nuevo UUID' => $newId,
                'Username' => $user->username ?? 'N/A',
                'Email' => $user->email ?? 'N/A',
                'Nombre' => ($user->full_name ?? $user->name ?? 'N/A'),
            ];
        }

        $this->table(['ID Antiguo', 'Nuevo UUID', 'Username', 'Email', 'Nombre'], $table);

        if ($oldUsers->count() > 10) {
            $this->info("... y " . ($oldUsers->count() - 10) . " usuarios más");
        }
    }

    /**
     * Migrar usuarios con transformaciones
     */
    protected function migrateUsers($oldUsers, bool $generateUuids, string $defaultPassword): void
    {
        $progressBar = $this->output->createProgressBar($oldUsers->count());
        $progressBar->start();

        $migrated = 0;
        $errors = 0;
        $idMapping = []; // Para guardar mapeo de IDs antiguos a UUIDs nuevos

        foreach ($oldUsers as $oldUser) {
            try {
                $newUser = $this->transformUser($oldUser, $generateUuids, $defaultPassword);

                // Guardar mapeo de IDs
                if (isset($oldUser->id)) {
                    $idMapping[$oldUser->id] = $newUser['id'];
                }

                // Verificar si el usuario ya existe
                $existing = DB::connection('mysql')
                    ->table('users')
                    ->where('email', $newUser['email'])
                    ->orWhere('username', $newUser['username'])
                    ->first();

                if ($existing) {
                    $this->warn("\n⚠️  Usuario ya existe: {$newUser['email']}");
                    continue;
                }

                // Insertar usuario
                DB::connection('mysql')->table('users')->insert($newUser);
                $migrated++;

            } catch (Exception $e) {
                $errors++;
                $email = $oldUser->email ?? 'unknown';
                $this->error("\n❌ Error migrando usuario {$email}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        // Guardar mapeo de IDs para futuras migraciones
        if (!empty($idMapping)) {
            file_put_contents(
                storage_path('app/user_id_mapping.json'),
                json_encode($idMapping, JSON_PRETTY_PRINT)
            );
            $this->info("\n📄 Mapeo de IDs guardado en storage/app/user_id_mapping.json");
        }

        $this->info("\n📊 Resultados:");
        $this->info("✅ Usuarios migrados: $migrated");
        $this->info("❌ Errores: $errors");
    }

    /**
     * Transformar un usuario de BD antigua a formato nuevo
     */
    protected function transformUser($oldUser, bool $generateUuids, string $defaultPassword): array
    {
        $newUser = [];

        // ID - generar UUID si se especifica o si el ID antiguo es numérico
        if ($generateUuids || (isset($oldUser->id) && is_numeric($oldUser->id))) {
            $newUser['id'] = Str::uuid()->toString();
        } else {
            $newUser['id'] = $oldUser->id ?? Str::uuid()->toString();
        }

        // Campos básicos
        $newUser['username'] = $oldUser->username ?? $oldUser->email ?? 'user_' . time();
        $newUser['email'] = $oldUser->email;
        $newUser['full_name'] = $oldUser->full_name ?? $oldUser->name ?? $oldUser->first_name . ' ' . $oldUser->last_name ?? 'Usuario';

        // Password - usar el existente o generar uno por defecto
        if (isset($oldUser->password) && !empty($oldUser->password)) {
            // Si la password ya está hasheada, usarla tal como está
            $newUser['password'] = (strlen($oldUser->password) === 60 && str_starts_with($oldUser->password, '$2y$'))
                ? $oldUser->password
                : Hash::make($oldUser->password);
        } else {
            $newUser['password'] = Hash::make($defaultPassword);
        }

        // Roles - transformar según la BD antigua
        $newUser['roles'] = $this->transformRoles($oldUser);

        // Plan - mapear plan antiguo a nuevo
        $newUser['plan'] = $oldUser->plan ?? 'free';
        $newUser['plan_code'] = $oldUser->plan_code ?? $oldUser->plan ?? 'free';

        // Fechas
        $newUser['created_at'] = $oldUser->created_at ?? now();
        $newUser['updated_at'] = $oldUser->updated_at ?? now();

        // Campos específicos de Juntify
        $newUser['legal_accepted_at'] = $oldUser->legal_accepted_at ?? null;
        $newUser['plan_expires_at'] = $oldUser->plan_expires_at ?? null;
        $newUser['blocked_at'] = $oldUser->blocked_at ?? null;
        $newUser['blocked_until'] = $oldUser->blocked_until ?? null;
        $newUser['blocked_permanent'] = $oldUser->blocked_permanent ?? false;
        $newUser['blocked_reason'] = $oldUser->blocked_reason ?? null;
        $newUser['blocked_by'] = $oldUser->blocked_by ?? null;
        $newUser['current_organization_id'] = $oldUser->current_organization_id ?? null;
        $newUser['is_role_protected'] = $oldUser->is_role_protected ?? false;

        return $newUser;
    }

    /**
     * Transformar roles de formato antiguo a nuevo
     */
    protected function transformRoles($oldUser): string
    {
        // Si ya tiene roles definidos
        if (isset($oldUser->roles) && !empty($oldUser->roles)) {
            return $oldUser->roles;
        }

        // Mapear según campos de la BD antigua
        if (isset($oldUser->is_admin) && $oldUser->is_admin) {
            return 'admin';
        }

        if (isset($oldUser->role)) {
            return $oldUser->role;
        }

        if (isset($oldUser->user_type)) {
            return $oldUser->user_type;
        }

        // Por defecto, usuario normal
        return 'user';
    }
}
