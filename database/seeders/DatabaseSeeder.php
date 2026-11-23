<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan penting: roles -> users (butuh role_id) -> rules
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
        ]);
    }
}
