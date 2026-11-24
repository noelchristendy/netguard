<?php

namespace Tests\Unit;

use App\Services\RuleMatcher;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RuleMatcherTest extends TestCase
{
    public function test_match_ip(): void
    {
        $cases = [
            // [ip, cidr, diharapkan]
            ['192.168.1.10',  null,               true],   // atribut kosong = semua
            ['192.168.1.10',  '',                 true],
            [null,            null,               true],
            ['192.168.1.10',  '192.168.1.0/24',   true],
            ['192.168.1.0',   '192.168.1.0/24',   true],   // batas bawah
            ['192.168.1.255', '192.168.1.0/24',   true],   // batas atas
            ['192.168.0.255', '192.168.1.0/24',   false],  // tepat di luar
            ['192.168.2.1',   '192.168.1.0/24',   false],
            ['10.0.0.5',      '10.0.0.5',         true],   // IP tunggal = /32
            ['10.0.0.6',      '10.0.0.5',         false],
            ['10.0.0.5',      '10.0.0.5/32',      true],
            ['10.0.0.5',      '10.0.0.4/31',      true],
            ['10.0.0.6',      '10.0.0.4/31',      false],
            ['8.8.8.8',       '0.0.0.0/0',        true],   // /0 = semua IPv4
            ['192.168.1.10',  '192.168.1.77/24',  true],   // bit host pada CIDR diabaikan
            ['999.1.1.1',     '192.168.1.0/24',   false],  // IP trafik tidak valid
            [null,            '192.168.1.0/24',   false],
            ['::1',           '192.168.1.0/24',   false],  // IPv6 di luar ruang lingkup
            ['192.168.1.10',  '192.168.1.0/33',   false],  // prefix tidak valid
            ['192.168.1.10',  '192.168.1.0/abc',  false],
            ['192.168.1.10',  '192.168.1.0/',     false],
            ['192.168.1.10',  'bukan-ip/24',      false],
        ];

        foreach ($cases as [$ip, $cidr, $expected]) {
            $this->assertSame($expected, RuleMatcher::matchIp($ip, $cidr), 'matchIp('.var_export($ip, true).', '.var_export($cidr, true).')');
        }
    }

    public function test_match_protocol(): void
    {
        $cases = [
            ['TCP',  null,  true],
            ['TCP',  '',    true],
            ['TCP',  'any', true],
            ['UDP',  'ANY', true],
            [null,   'any', true],
            ['TCP',  'TCP', true],
            ['tcp',  'TCP', true],   // tidak peka huruf besar kecil
            ['TCP',  'UDP', false],
            ['ICMP', 'TCP', false],
            [null,   'TCP', false],
        ];

        foreach ($cases as [$traffic, $rule, $expected]) {
            $this->assertSame($expected, RuleMatcher::matchProtocol($traffic, $rule), 'matchProtocol('.var_export($traffic, true).', '.var_export($rule, true).')');
        }
    }

    public function test_match_port(): void
    {
        $cases = [
            [22,    null,  true],    // rule tanpa port = semua port
            [22,    '',    true],
            [null,  null,  true],
            [22,    22,    true],
            ['22',  22,    true],    // nilai dari CSV berupa string
            [22,    '22',  true],
            [443,   22,    false],
            [null,  22,    false],   // ICMP tanpa port tidak cocok dengan rule bernomor port
            ['',    22,    false],
            ['abc', 22,    false],
        ];

        foreach ($cases as [$traffic, $rule, $expected]) {
            $this->assertSame($expected, RuleMatcher::matchPort($traffic, $rule), 'matchPort('.var_export($traffic, true).', '.var_export($rule, true).')');
        }
    }

    public function test_match_time_window_melewati_tengah_malam(): void
    {
        // Jendela 17:00-08:00 (start inklusif, end eksklusif)
        $cases = [
            ['00:00:00', true],
            ['03:12:00', true],
            ['07:59:59', true],
            ['08:00:00', false],   // tepi akhir: sudah jam kerja
            ['12:00:00', false],
            ['16:59:59', false],
            ['17:00:00', true],    // tepi awal: masuk jendela
            ['17:00:01', true],
            ['22:41:37', true],
            ['23:59:59', true],
        ];

        foreach ($cases as [$time, $expected]) {
            $this->assertSame($expected, RuleMatcher::matchTime($time, '17:00:00', '08:00:00'), "jam $time pada jendela 17:00-08:00");
        }
    }

    public function test_match_time_window_biasa(): void
    {
        $cases = [
            ['07:59:59', false],
            ['08:00:00', true],
            ['12:30:00', true],
            ['16:59:59', true],
            ['17:00:00', false],
        ];

        foreach ($cases as [$time, $expected]) {
            $this->assertSame($expected, RuleMatcher::matchTime($time, '08:00', '17:00'), "jam $time pada jendela 08:00-17:00");
        }
    }

    public function test_match_time_menerima_berbagai_format(): void
    {
        $this->assertTrue(RuleMatcher::matchTime('2026-11-20 22:41:37', '17:00:00', '08:00:00'));
        $this->assertFalse(RuleMatcher::matchTime('2026-11-20 10:02:11', '17:00:00', '08:00:00'));
        $this->assertTrue(RuleMatcher::matchTime(new DateTimeImmutable('2026-11-20 22:41:37'), '17:00', '08:00'));
        $this->assertFalse(RuleMatcher::matchTime(new DateTimeImmutable('2026-11-20 08:00:00'), '17:00', '08:00'));
    }

    public function test_match_time_tanpa_jendela_selalu_cocok(): void
    {
        $this->assertTrue(RuleMatcher::matchTime('12:00:00', null, null));
        $this->assertTrue(RuleMatcher::matchTime(null, null, null));
        $this->assertTrue(RuleMatcher::matchTime('12:00:00', '', ''));
    }

    public function test_match_time_trafik_tanpa_waktu_tidak_cocok_bila_rule_punya_jendela(): void
    {
        $this->assertFalse(RuleMatcher::matchTime(null, '17:00', '08:00'));
        $this->assertFalse(RuleMatcher::matchTime('bukan jam', '17:00', '08:00'));
    }

    public function test_match_time_jendela_tidak_valid_melempar_exception(): void
    {
        $invalid = [
            ['12:00', '17:00', null],        // hanya satu batas
            ['12:00', null, '08:00'],
            ['12:00', '08:00', '08:00'],     // start == end
            ['12:00', '25:00', '08:00'],     // jam di luar rentang
            ['12:00', 'abc', '08:00'],
        ];

        foreach ($invalid as [$time, $start, $end]) {
            try {
                RuleMatcher::matchTime($time, $start, $end);
                $this->fail('Seharusnya melempar InvalidArgumentException untuk '.var_export([$start, $end], true));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- Skenario dari rancangan (bagian 4.1 dan 4.2 laporan) ----

    private function rule(array $overrides): array
    {
        return array_merge([
            'src_ip' => null, 'dst_ip' => null, 'protocol' => 'any',
            'dst_port' => null, 'time_start' => null, 'time_end' => null,
        ], $overrides);
    }

    public function test_matches_skenario_laporan(): void
    {
        $r10  = $this->rule(['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443]);
        $r20  = $this->rule(['protocol' => 'TCP', 'dst_port' => 22, 'time_start' => '17:00:00', 'time_end' => '08:00:00']);
        $r30  = $this->rule(['protocol' => 'ICMP']);
        $r999 = $this->rule([]);

        // Contoh laporan: 172.16.4.8 ke TCP 22 pukul 22:41 -> tidak cocok rule 10, cocok rule 20
        $ssh = ['timestamp' => '2026-11-20 22:41:37', 'src_ip' => '172.16.4.8', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 22];
        $this->assertFalse(RuleMatcher::matches($ssh, $r10));
        $this->assertTrue(RuleMatcher::matches($ssh, $r20));
        $this->assertFalse(RuleMatcher::matches($ssh, $r30));
        $this->assertTrue(RuleMatcher::matches($ssh, $r999));

        // U1: HTTPS dari LAN
        $https = ['timestamp' => '2026-11-20 09:15:02', 'src_ip' => '192.168.1.10', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 443];
        $this->assertTrue(RuleMatcher::matches($https, $r10));
        $this->assertFalse(RuleMatcher::matches($https, $r20));

        // HTTPS dari luar LAN tidak cocok rule 10
        $httpsLuar = ['timestamp' => '2026-11-20 09:15:02', 'src_ip' => '172.16.4.8', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 443];
        $this->assertFalse(RuleMatcher::matches($httpsLuar, $r10));

        // SSH di jam kerja tidak cocok rule 20
        $sshSiang = ['timestamp' => '2026-11-20 10:00:00', 'src_ip' => '203.0.113.10', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 22];
        $this->assertFalse(RuleMatcher::matches($sshSiang, $r20));

        // Batas jam pada data uji N2
        foreach (['07:59:59' => true, '08:00:00' => false, '17:00:00' => true, '17:00:01' => true] as $jam => $diharapkan) {
            $t = ['timestamp' => "2026-11-20 $jam", 'src_ip' => '203.0.113.10', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 22];
            $this->assertSame($diharapkan, RuleMatcher::matches($t, $r20), "SSH pukul $jam terhadap rule 20");
        }

        // ICMP tanpa port: cocok rule 30 dan 999, tidak cocok rule 10 dan 20
        $icmp = ['timestamp' => '2026-11-20 10:02:11', 'src_ip' => '192.168.1.25', 'dst_ip' => '10.0.0.9', 'protocol' => 'ICMP', 'dst_port' => null];
        $this->assertTrue(RuleMatcher::matches($icmp, $r30));
        $this->assertTrue(RuleMatcher::matches($icmp, $r999));
        $this->assertFalse(RuleMatcher::matches($icmp, $r10));
        $this->assertFalse(RuleMatcher::matches($icmp, $r20));
    }

    public function test_matches_menerima_rule_berupa_objek(): void
    {
        $rule = (object) $this->rule(['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443]);
        $traffic = ['timestamp' => '2026-11-20 09:15:02', 'src_ip' => '192.168.1.10', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => 443];

        $this->assertTrue(RuleMatcher::matches($traffic, $rule));
    }

    public function test_matches_dst_ip_diperiksa(): void
    {
        $rule = $this->rule(['dst_ip' => '10.0.0.5']);

        $this->assertTrue(RuleMatcher::matches(['src_ip' => '1.1.1.1', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP'], $rule));
        $this->assertFalse(RuleMatcher::matches(['src_ip' => '1.1.1.1', 'dst_ip' => '10.0.0.9', 'protocol' => 'TCP'], $rule));
    }
}
