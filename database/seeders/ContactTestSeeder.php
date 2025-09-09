<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ContactTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener usuario actual (si existe)
        $currentUser = User::first();
        $currentOrgId = $currentUser ? $currentUser->current_organization_id : 1;

        // Crear usuarios de prueba en la misma organización
        $testUsers = [
            [
                'full_name' => 'Gustavo Audi',
                'username' => 'gustavo.audi',
                'email' => 'gustavo.audi@juntify.com',
                'current_organization_id' => $currentOrgId,
            ],
            [
                'full_name' => 'Fernanda Soto',
                'username' => 'fernanda.soto',
                'email' => 'fernanda.soto@juntify.com',
                'current_organization_id' => $currentOrgId,
            ],
            [
                'full_name' => 'Leonardo Gomez',
                'username' => 'leonardo.gomez',
                'email' => 'leonardo.gomez@juntify.com',
                'current_organization_id' => 2, // Diferente organización
            ],
            [
                'full_name' => 'Raul Astorga',
                'username' => 'raul.astorga',
                'email' => 'raul.astorga@juntify.com',
                'current_organization_id' => 2, // Diferente organización
            ],
            [
                'full_name' => 'Maria Rodriguez',
                'username' => 'maria.rodriguez',
                'email' => 'maria.rodriguez@juntify.com',
                'current_organization_id' => $currentOrgId,
            ],
            [
                'full_name' => 'Carlos Mendez',
                'username' => 'carlos.mendez',
                'email' => 'carlos.mendez@juntify.com',
                'current_organization_id' => $currentOrgId,
            ]
        ];

        foreach ($testUsers as $userData) {
            // Solo crear si no existe
            if (!User::where('email', $userData['email'])->exists()) {
                User::create([
                    'full_name' => $userData['full_name'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password123'),
                    'roles' => 'user',
                    'current_organization_id' => $userData['current_organization_id'],
                ]);
            }
        }

        $this->command->info('Usuarios de prueba creados exitosamente.');
    }
}
