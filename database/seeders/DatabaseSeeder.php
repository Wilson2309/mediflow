<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,
            ClinicSeeder::class,
            SpecialtySeeder::class,
            ServiceSeeder::class,
            AdminClinicSeeder::class,
        ]);
    }
}
