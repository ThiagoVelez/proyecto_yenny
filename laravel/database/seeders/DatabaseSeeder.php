<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'estudiante@example.test'],
            [
                'name' => 'Estudiante Demo',
                'password' => \Illuminate\Support\Facades\Hash::make('ClaveDemo123!'),
            ]
        );
    }
}
