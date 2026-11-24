<?php

namespace Tests\Unit;

use App\Services\FilterService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FilterServiceTest extends TestCase
{
    // ---- fixture: 4 rule contoh dari bagian 4.1 laporan (id sengaja berbeda dari priority) ----

    private function rule(int $id, int $priority, array $cond, string $action, bool $enabled = true): array
    {
        return array_merge(
            ['src_ip' => null, 'dst_ip' => null, 'protocol' => 'any', 'dst_port' => null, 'time_start' => null, 'time_end' => null],
            $cond,
            ['id' => $id, 'priority' => $priority, 'action' => $action, 'enabled' => $enabled]
        );
    }

    private function rules(): Collection
    {
        return Collection::make([
            $this->rule(1, 10,  ['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443], 'allow'),
            $this->rule(2, 20,  ['protocol' => 'TCP', 'dst_port' => 22, 'time_start' => '17:00:00', 'time_end' => '08:00:00'], 'deny'),
            $this->rule(3, 30,  ['protocol' => 'ICMP'], 'log'),
            $this->rule(4, 999, [], 'deny'),
        ]);
    }

    private function traffic(string $ts, string $src, string $dst, string $proto, ?int $port): array
    {
        return ['timestamp' => $ts, 'src_ip' => $src, 'dst_ip' => $dst, 'protocol' => $proto, 'dst_port' => $port];
    }

    // ---- U1 sampai U4 ----

    public function test_u1_https_dari_lan_allow_oleh_rule_10(): void
    {
        $r = FilterService::evaluate($this->traffic('2026-11-20 09:15:02', '192.168.1.10', '10.0.0.5', 'TCP', 443), $this->rules());

        $this->assertSame(['decision' => 'allow', 'rule_id' => 1, 'logged_rule_ids' => [], 'is_default' => false], $r);
    }

    public function test_u2_ssh_luar_jam_deny_oleh_rule_20(): void
    {
        // Contoh laporan: 172.16.4.8 ke TCP 22 pukul 22:41
        $r = FilterService::evaluate($this->traffic('2026-11-20 22:41:37', '172.16.4.8', '10.0.0.5', 'TCP', 22), $this->rules());

        $this->assertSame(['decision' => 'deny', 'rule_id' => 2, 'logged_rule_ids' => [], 'is_default' => false], $r);
    }

    public function test_u3_tanpa_rule_cocok_default_deny(): void
    {
        // Rule 999 (catch-all) sengaja tidak ada, kalau ada ia menangkap semuanya.
        $tanpaCatchAll = $this->rules()->take(3);

        $r = FilterService::evaluate($this->traffic('2026-11-20 10:00:00', '192.168.1.10', '10.0.0.53', 'UDP', 53), $tanpaCatchAll);

        $this->assertSame(['decision' => 'deny', 'rule_id' => null, 'logged_rule_ids' => [], 'is_default' => true], $r);
    }

    public function test_u3_tanpa_rule_sama_sekali_default_deny(): void
    {
        $r = FilterService::evaluate($this->traffic('2026-11-20 10:00:00', '1.1.1.1', '2.2.2.2', 'TCP', 80), Collection::make());

        $this->assertTrue($r['is_default']);
        $this->assertSame('deny', $r['decision']);
        $this->assertNull($r['rule_id']);
    }

    public function test_u4_rule_berprioritas_lebih_kecil_dipakai(): void
    {
        $deny5   = $this->rule(11, 5,  ['src_ip' => '192.168.1.0/24'], 'deny');
        $allow10 = $this->rule(12, 10, [], 'allow');
        $t = $this->traffic('2026-11-20 10:00:00', '192.168.1.10', '10.0.0.5', 'TCP', 80);

        // Urutan di koleksi sengaja dibalik: yang menentukan priority, bukan posisi.
        $r = FilterService::evaluate($t, Collection::make([$allow10, $deny5]));

        $this->assertSame('deny', $r['decision']);
        $this->assertSame(11, $r['rule_id']);
    }

    // ---- aksi log ----

    public function test_icmp_dicatat_rule_30_lalu_deny_oleh_rule_999(): void
    {
        $r = FilterService::evaluate($this->traffic('2026-11-20 10:02:11', '192.168.1.25', '10.0.0.9', 'ICMP', null), $this->rules());

        $this->assertSame(['decision' => 'deny', 'rule_id' => 4, 'logged_rule_ids' => [3], 'is_default' => false], $r);
    }

    public function test_log_tanpa_rule_penentu_berakhir_default_deny(): void
    {
        $r = FilterService::evaluate(
            $this->traffic('2026-11-20 10:02:11', '192.168.1.25', '10.0.0.9', 'ICMP', null),
            Collection::make([$this->rule(3, 30, ['protocol' => 'ICMP'], 'log')])
        );

        $this->assertSame(['decision' => 'deny', 'rule_id' => null, 'logged_rule_ids' => [3], 'is_default' => true], $r);
    }

    public function test_beberapa_rule_log_tercatat_berurutan_menurut_priority(): void
    {
        $rules = Collection::make([
            $this->rule(22, 7, [], 'allow'),
            $this->rule(21, 6, [], 'log'),
            $this->rule(20, 5, [], 'log'),
        ]);

        $r = FilterService::evaluate($this->traffic('2026-11-20 10:00:00', '1.1.1.1', '2.2.2.2', 'TCP', 80), $rules);

        $this->assertSame('allow', $r['decision']);
        $this->assertSame(22, $r['rule_id']);
        $this->assertSame([20, 21], $r['logged_rule_ids']);
    }

    public function test_allow_menghentikan_evaluasi_sehingga_rule_log_sesudahnya_tidak_tercatat(): void
    {
        $rules = Collection::make([
            $this->rule(30, 1, [], 'allow'),
            $this->rule(31, 2, [], 'log'),
        ]);

        $r = FilterService::evaluate($this->traffic('2026-11-20 10:00:00', '1.1.1.1', '2.2.2.2', 'TCP', 80), $rules);

        $this->assertSame([], $r['logged_rule_ids']);
        $this->assertSame(30, $r['rule_id']);
    }

    // ---- rule nonaktif, batas jam, aksi tidak dikenal ----

    public function test_rule_nonaktif_diabaikan(): void
    {
        $rules = Collection::make([
            $this->rule(1, 10,  ['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443], 'allow', false),
            $this->rule(9, 5,   [], 'deny', false),   // deny nonaktif di prioritas teratas tidak boleh memblokir
            $this->rule(4, 999, [], 'deny'),
        ]);

        $r = FilterService::evaluate($this->traffic('2026-11-20 09:15:02', '192.168.1.10', '10.0.0.5', 'TCP', 443), $rules);

        $this->assertSame(4, $r['rule_id']);   // rule 10 nonaktif, jatuh ke rule 999
    }

    public function test_batas_jam_ssh_pada_jendela_17_sampai_08(): void
    {
        $cases = [
            '07:59:59' => 2,   // masih di dalam jendela: rule 20
            '08:00:00' => 4,   // jam kerja dimulai: jatuh ke rule 999
            '16:59:59' => 4,
            '17:00:00' => 2,   // jendela dimulai: rule 20
            '17:00:01' => 2,
        ];

        foreach ($cases as $jam => $ruleId) {
            $r = FilterService::evaluate($this->traffic("2026-11-20 $jam", '203.0.113.10', '10.0.0.5', 'TCP', 22), $this->rules());
            $this->assertSame($ruleId, $r['rule_id'], "SSH pukul $jam");
            $this->assertSame('deny', $r['decision']);
        }
    }

    public function test_aksi_tidak_dikenal_melempar_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterService::evaluate(
            $this->traffic('2026-11-20 10:00:00', '1.1.1.1', '2.2.2.2', 'TCP', 80),
            Collection::make([$this->rule(1, 1, [], 'block')])
        );
    }

    public function test_rule_berupa_objek_seperti_model(): void
    {
        $rules = Collection::make([(object) $this->rule(1, 10, ['protocol' => 'TCP', 'dst_port' => 443], 'allow')]);

        $r = FilterService::evaluate($this->traffic('2026-11-20 10:00:00', '1.1.1.1', '2.2.2.2', 'TCP', 443), $rules);

        $this->assertSame('allow', $r['decision']);
        $this->assertSame(1, $r['rule_id']);
    }

    // ---- evaluateMany, encodeLoggedIds, kinerja ----

    public function test_evaluate_many_sama_dengan_evaluate_satu_per_satu(): void
    {
        $traffics = [
            $this->traffic('2026-11-20 09:15:02', '192.168.1.10', '10.0.0.5', 'TCP', 443),
            $this->traffic('2026-11-20 22:41:37', '172.16.4.8', '10.0.0.5', 'TCP', 22),
            $this->traffic('2026-11-20 10:02:11', '192.168.1.25', '10.0.0.9', 'ICMP', null),
        ];

        $many = FilterService::evaluateMany($traffics, $this->rules());

        $this->assertCount(3, $many);
        foreach ($traffics as $i => $t) {
            $this->assertSame(FilterService::evaluate($t, $this->rules()), $many[$i]);
        }
    }

    public function test_encode_logged_ids_null_bila_kosong(): void
    {
        $this->assertNull(FilterService::encodeLoggedIds([]));
        $this->assertSame('[3]', FilterService::encodeLoggedIds([3]));
        $this->assertSame('[3,5]', FilterService::encodeLoggedIds([3, 5]));
    }

    public function test_kinerja_10000_baris_kurang_dari_5_detik(): void
    {
        $traffics = [];
        for ($i = 0; $i < 10000; $i++) {
            $icmp = $i % 3 === 0;
            $traffics[] = $this->traffic(
                sprintf('2026-11-20 %02d:%02d:%02d', $i % 24, $i % 60, $i % 60),
                '192.168.1.'.(($i % 250) + 1),
                '10.0.0.5',
                $icmp ? 'ICMP' : 'TCP',
                $icmp ? null : [22, 443, 80][$i % 3]
            );
        }

        $start   = hrtime(true);
        $results = FilterService::evaluateMany($traffics, $this->rules());
        $seconds = (hrtime(true) - $start) / 1e9;

        $this->assertCount(10000, $results);
        $this->assertLessThan(5.0, $seconds, "10.000 baris memakan {$seconds} detik");
    }
}
