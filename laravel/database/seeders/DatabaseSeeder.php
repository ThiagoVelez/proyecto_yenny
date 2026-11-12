<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('ClaveDemo123!');

        // 1. Perfil Administrador (Acceso total REST y SOAP)
        User::updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name'     => 'Administrador Sistema',
                'password' => $defaultPassword,
                'role'     => 'admin',
            ]
        );

        // 2. Perfil Operador de Recepcion (Gestion de cobros)
        User::updateOrCreate(
            ['email' => 'operador@example.test'],
            [
                'name'     => 'Operador de Recepcion',
                'password' => $defaultPassword,
                'role'     => 'operador_recepcion',
            ]
        );

        // 3. Perfil Cajero (Procesamiento de pagos)
        User::updateOrCreate(
            ['email' => 'cajero@example.test'],
            [
                'name'     => 'Cajero de Pagos',
                'password' => $defaultPassword,
                'role'     => 'cajero',
            ]
        );

        // 4. Perfil Cliente (Visualizacion de alquileres y consultas)
        User::updateOrCreate(
            ['email' => 'cliente@example.test'],
            [
                'name'     => 'Cliente Registrado',
                'password' => $defaultPassword,
                'role'     => 'cliente',
            ]
        );

        // 5. Usuario demo para pruebas previas
        User::updateOrCreate(
            ['email' => 'estudiante@example.test'],
            [
                'name'     => 'Estudiante Demo',
                'password' => $defaultPassword,
                'role'     => 'admin',
            ]
        );
    }
}
