<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::ADMINISTRATOR => 'Mengelola pengguna, rule, dan melihat seluruh log.',
            Role::OPERATOR      => 'Membuat dan mengubah rule, menjalankan penyaringan.',
            Role::AUDITOR       => 'Membaca log keputusan dan log audit.',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
