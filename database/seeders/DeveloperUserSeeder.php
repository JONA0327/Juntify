<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Models\User;

class DeveloperUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $email = 'jona03278@gmail.com';
        $username = 'Jona0327';

        User::updateOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'full_name' => 'Jonathan Israel Loredo Palacios',
                'password' => Hash::make('Pass_123456'),
                'roles' => 'developer',
                'plan' => 'developer',
                'plan_code' => 'developer',
                'is_role_protected' => true,
                'legal_accepted_at' => Carbon::now(),
            ]
        );
    }
}
