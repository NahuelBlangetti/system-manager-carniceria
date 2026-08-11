<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@carniceria.com'],
            [
                'name'              => 'Administrador',
                'password'          => 'carniceria',
                'email_verified_at' => now(),
            ]
        );
    }
}
