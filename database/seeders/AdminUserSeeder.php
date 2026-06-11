<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@mediflow.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin123*'),
            ]
        );

        $admin->assignRole('administrador');
    }
}