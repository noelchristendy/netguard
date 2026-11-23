<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun contoh untuk pengembangan dan demo. JANGAN dipakai di lingkungan sungguhan:
 * ganti kata sandinya atau hapus seeder ini sebelum deployment.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['username' => 'admin',    'name' => 'Administrator', 'role' => Role::ADMINISTRATOR, 'password' => 'Admin#2026'],
            ['username' => 'operator', 'name' => 'Operator',      'role' => Role::OPERATOR,      'password' => 'Operator#2026'],
            ['username' => 'auditor',  'name' => 'Auditor',       'role' => Role::AUDITOR,       'password' => 'Auditor#2026'],
        ];

        foreach ($accounts as $a) {
            User::updateOrCreate(
                ['username' => $a['username']],
                [
                    'name'            => $a['name'],
                    'password'        => Hash::make($a['password']),
                    'role_id'         => Role::where('name', $a['role'])->value('id'),
                    'failed_attempts' => 0,
                    'is_locked'       => false,
                    'locked_until'    => null,
                ]
            );
        }
    }
}
