<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Superadmin
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('admin123*'),
                'roles' => ['superadmin']
            ]
        );

        // 2. Cliente
        User::updateOrCreate(
            ['email' => 'cliente@test.com'],
            [
                'name' => 'Cliente de Prueba',
                'password' => Hash::make('cliente123*'),
                'roles' => ['cliente']
            ]
        );

        // 3. Operativo
        User::updateOrCreate(
            ['email' => 'operativo@test.com'],
            [
                'name' => 'Operativo de Factoring',
                'password' => Hash::make('operativo123*'),
                'roles' => ['operativo']
            ]
        );

        // 4. Gerente
        User::updateOrCreate(
            ['email' => 'gerente@test.com'],
            [
                'name' => 'Gerencia Financiera',
                'password' => Hash::make('gerente123*'),
                'roles' => ['gerente']
            ]
        );

        // 5. Multi-role User (Gerente + Operativo)
        User::updateOrCreate(
            ['email' => 'multi@test.com'],
            [
                'name' => 'Usuario Multiperfil',
                'password' => Hash::make('multi123*'),
                'roles' => ['gerente', 'operativo']
            ]
        );
    }
}
