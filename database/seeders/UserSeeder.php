<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $email    = env('ADMIN_EMAIL');
            $password = env('ADMIN_PASSWORD');

            if (blank($email) || blank($password)) {
                throw new \RuntimeException(
                    'Definí ADMIN_EMAIL y ADMIN_PASSWORD en el .env de producción antes de sembrar.'
                );
            }

            $name = env('ADMIN_NAME', 'Administrador');
        } else {
            $email    = env('ADMIN_EMAIL', 'admin@carniceria-emanuel.com');
            $password = env('ADMIN_PASSWORD', 'Emanuel465');
            $name     = env('ADMIN_NAME', 'Administrador');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => $password,
                'email_verified_at' => now(),
            ]
        );
    }
}
