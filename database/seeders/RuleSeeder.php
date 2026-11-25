<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rules = [
            [
                'priority' => 10,
                'src_ip' => '192.168.1.0/24',
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 443,
                'time_start' => null,
                'time_end' => null,
                'action' => 'allow',
                'enabled' => true,
                'description' => 'Allow HTTPS dari jaringan LAN 192.168.1.0/24',
                'created_by' => null,
            ],
            [
                'priority' => 20,
                'src_ip' => null,
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 22,
                'time_start' => '17:00:00',
                'time_end' => '08:00:00',
                'action' => 'deny',
                'enabled' => true,
                'description' => 'Deny SSH di luar jam kerja (17:00-08:00)',
                'created_by' => null,
            ],
            [
                'priority' => 30,
                'src_ip' => null,
                'dst_ip' => null,
                'protocol' => 'ICMP',
                'dst_port' => null,
                'time_start' => null,
                'time_end' => null,
                'action' => 'log',
                'enabled' => true,
                'description' => 'Catat seluruh trafik ICMP',
                'created_by' => null,
            ],
            [
                'priority' => 999,
                'src_ip' => null,
                'dst_ip' => null,
                'protocol' => 'any',
                'dst_port' => null,
                'time_start' => null,
                'time_end' => null,
                'action' => 'deny',
                'enabled' => true,
                'description' => 'Default deny untuk seluruh trafik lainnya',
                'created_by' => null,
            ],
        ];

        foreach ($rules as $rule) {
            $exists = DB::table('rules')
                ->where('priority', $rule['priority'])
                ->exists();

            $values = array_merge($rule, [
                'updated_at' => $now,
            ]);

            if (!$exists) {
                $values['created_at'] = $now;
            }

            DB::table('rules')->updateOrInsert(
                ['priority' => $rule['priority']],
                $values
            );
        }

        $this->command?->info('Empat rule NetGuard berhasil disiapkan.');
    }
}
