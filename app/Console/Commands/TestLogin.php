<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TestLogin extends Command
{
    protected $signature = 'test:login {email} {password}';
    protected $description = 'Test login credentials for debugging';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->orWhere('username', $email)->first();

        if (!$user) {
            $this->error("Usuario no encontrado: {$email}");
            return 1;
        }

        $this->info("✓ Usuario encontrado: {$user->email}");
        $this->info("  ID: {$user->id}");
        $this->info("  Username: {$user->username}");
        $this->info("  Full Name: {$user->full_name}");
        $this->info("  Password Hash: " . substr($user->password, 0, 20) . "...");

        if (password_verify($password, $user->password)) {
            $this->info("✓ La contraseña es CORRECTA");
            return 0;
        } else {
            $this->error("✗ La contraseña es INCORRECTA");
            $this->warn("Intenta verificar que la contraseña sea exacta (mayúsculas, espacios, etc.)");
            return 1;
        }
    }
}
