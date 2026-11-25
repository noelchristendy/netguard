<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 10 rule NetGuard. Urutan prioritas sengaja diatur agar skenario first match terlihat jelas:
 *
 *   5   deny   blocklist 203.0.113.0/24            (paling atas, mengalahkan semua allow)
 *   10  allow  HTTPS dari LAN
 *   15  allow  SSH dari jaringan admin             (sebelum rule 20, jadi lolos walau di luar jam kerja)
 *   20  deny   SSH di luar jam kerja
 *   25  allow  DNS ke server DNS internal
 *   30  log    ICMP                                (dicatat, evaluasi lanjut)
 *   35  allow  HTTP dari LAN pada jam kerja
 *   40  deny   Telnet
 *   50  log    RDP                                 (dicatat, evaluasi lanjut ke rule 999)
 *   999 deny   semua trafik lain
 *
 * Aman dijalankan berulang kali: rule dicocokkan berdasarkan priority (updateOrInsert).
 * Rule lain dengan priority di luar daftar ini tidak disentuh.
 */
class RuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rules = [
            [
                'priority' => 5,
                'src_ip' => '203.0.113.0/24',
                'dst_ip' => null,
                'protocol' => 'any',
                'dst_port' => null,
                'time_start' => null,
                'time_end' => null,
                'action' => 'deny',
                'enabled' => true,
                'description' => 'Blocklist: tolak semua trafik dari 203.0.113.0/24',
            ],
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
            ],
            [
                'priority' => 15,
                'src_ip' => '10.10.10.0/24',
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 22,
                'time_start' => null,
                'time_end' => null,
                'action' => 'allow',
                'enabled' => true,
                'description' => 'Allow SSH dari jaringan admin 10.10.10.0/24 kapan saja',
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
            ],
            [
                'priority' => 25,
                'src_ip' => null,
                'dst_ip' => '10.0.0.53',
                'protocol' => 'UDP',
                'dst_port' => 53,
                'time_start' => null,
                'time_end' => null,
                'action' => 'allow',
                'enabled' => true,
                'description' => 'Allow DNS (UDP 53) ke server DNS internal 10.0.0.53',
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
            ],
            [
                'priority' => 35,
                'src_ip' => '192.168.1.0/24',
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 80,
                'time_start' => '08:00:00',
                'time_end' => '17:00:00',
                'action' => 'allow',
                'enabled' => true,
                'description' => 'Allow HTTP dari LAN pada jam kerja (08:00-17:00)',
            ],
            [
                'priority' => 40,
                'src_ip' => null,
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 23,
                'time_start' => null,
                'time_end' => null,
                'action' => 'deny',
                'enabled' => true,
                'description' => 'Deny Telnet (tidak terenkripsi)',
            ],
            [
                'priority' => 50,
                'src_ip' => null,
                'dst_ip' => null,
                'protocol' => 'TCP',
                'dst_port' => 3389,
                'time_start' => null,
                'time_end' => null,
                'action' => 'log',
                'enabled' => true,
                'description' => 'Catat seluruh upaya RDP (port 3389)',
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
            ],
        ];

        foreach ($rules as $rule) {
            $exists = DB::table('rules')
                ->where('priority', $rule['priority'])
                ->exists();

            $values = array_merge($rule, [
                'created_by' => null,
                'updated_at' => $now,
            ]);

            if (! $exists) {
                $values['created_at'] = $now;
            }

            DB::table('rules')->updateOrInsert(
                ['priority' => $rule['priority']],
                $values
            );
        }

        $this->command?->info(count($rules).' rule NetGuard berhasil disiapkan.');
    }
}
