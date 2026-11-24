<?php

namespace Tests\Feature;

use App\Http\Requests\StoreRuleRequest;
use App\Services\RuleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PERHATIAN: RefreshDatabase mengosongkan database. Pastikan phpunit.xml memakai database
 * terpisah (sqlite :memory:) sebelum menjalankan tes ini. Lihat langkah 13.3.
 */
class StoreRuleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'priority'    => 100,
            'src_ip'      => '192.168.1.0/24',
            'dst_ip'      => null,
            'protocol'    => 'TCP',
            'dst_port'    => 443,
            'time_start'  => null,
            'time_end'    => null,
            'action'      => 'allow',
            'enabled'     => true,
            'description' => 'HTTPS dari LAN',
        ], $overrides);
    }

    /** Menjalankan validasi FormRequest persis seperti saat dipakai di controller. */
    private function validateRequest(array $data, int|string|null $routeRuleId = null): StoreRuleRequest
    {
        $request = StoreRuleRequest::create('/rules', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));

        if ($routeRuleId !== null) {
            // Meniru parameter rute {rule} saat mengubah rule yang sudah ada
            $request->setRouteResolver(fn () => new class($routeRuleId) {
                public function __construct(private int|string $id) {}

                public function parameter($key, $default = null)
                {
                    return $this->id;
                }
            });
        }

        $request->validateResolved();

        return $request;
    }

    /** @return array<string, string[]> daftar galat per kolom, kosong bila lolos */
    private function errors(array $data, int|string|null $routeRuleId = null): array
    {
        try {
            $this->validateRequest($data, $routeRuleId);

            return [];
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    private function insertRule(int $priority): int
    {
        return DB::table('rules')->insertGetId([
            'priority'   => $priority,
            'protocol'   => 'TCP',
            'action'     => 'allow',
            'enabled'    => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ tes

    public function test_data_valid_lolos(): void
    {
        $this->assertSame([], $this->errors($this->valid()));
    }

    public function test_rule_catch_all_tanpa_kondisi_lolos(): void
    {
        $data = $this->valid([
            'priority' => 999, 'src_ip' => null, 'dst_ip' => null, 'protocol' => 'any',
            'dst_port' => null, 'action' => 'deny',
        ]);

        $this->assertSame([], $this->errors($data));
    }

    public function test_ip_dan_cidr_valid_diterima(): void
    {
        foreach (['10.0.0.5', '10.0.0.0/8', '0.0.0.0/0', '192.168.1.0/24', '10.0.0.5/32', '192.168.1.77/24'] as $value) {
            $this->assertSame([], $this->errors($this->valid(['src_ip' => $value])), "src_ip $value");
            $this->assertSame([], $this->errors($this->valid(['dst_ip' => $value])), "dst_ip $value");
        }
    }

    public function test_ip_dan_cidr_tidak_valid_ditolak(): void
    {
        $invalid = ['999.1.1.1', '192.168.1.0/33', '192.168.1.0/', '192.168.1.0/abc', 'abc', '::1', '192.168.1.1/24/8', '10.0.0', '10.0.0.5/-1'];

        foreach ($invalid as $value) {
            $this->assertArrayHasKey('src_ip', $this->errors($this->valid(['src_ip' => $value])), "src_ip $value");
            $this->assertArrayHasKey('dst_ip', $this->errors($this->valid(['dst_ip' => $value])), "dst_ip $value");
        }
    }

    public function test_port_valid_dan_tidak_valid(): void
    {
        foreach ([1, 22, 443, 65535, '8080'] as $port) {
            $this->assertSame([], $this->errors($this->valid(['dst_port' => $port])), "port $port");
        }

        foreach ([0, -1, 65536, 'abc', '22.5'] as $port) {
            $this->assertArrayHasKey('dst_port', $this->errors($this->valid(['dst_port' => $port])), "port $port");
        }
    }

    public function test_icmp_tidak_boleh_punya_port(): void
    {
        $this->assertArrayHasKey('dst_port', $this->errors($this->valid(['protocol' => 'ICMP', 'dst_port' => 22])));
        $this->assertSame([], $this->errors($this->valid(['protocol' => 'ICMP', 'dst_port' => null])));
        $this->assertSame([], $this->errors($this->valid(['protocol' => 'any', 'dst_port' => 22])));
    }

    public function test_protokol(): void
    {
        foreach (['TCP', 'UDP', 'ICMP', 'any', 'tcp', 'ANY'] as $protocol) {
            $this->assertSame([], $this->errors($this->valid(['protocol' => $protocol, 'dst_port' => null])), "protokol $protocol");
        }

        $this->assertArrayHasKey('protocol', $this->errors($this->valid(['protocol' => 'HTTP'])));
        $this->assertArrayHasKey('protocol', $this->errors($this->valid(['protocol' => null])));
    }

    public function test_aksi(): void
    {
        foreach (['allow', 'deny', 'log', 'ALLOW'] as $action) {
            $this->assertSame([], $this->errors($this->valid(['action' => $action])), "aksi $action");
        }

        $this->assertArrayHasKey('action', $this->errors($this->valid(['action' => 'block'])));
        $this->assertArrayHasKey('action', $this->errors($this->valid(['action' => null])));
    }

    public function test_jam_valid(): void
    {
        $windows = [[null, null], ['08:00', '17:00'], ['17:00', '08:00'], ['17:00:00', '08:00:00'], ['00:00', '23:59']];

        foreach ($windows as [$start, $end]) {
            $this->assertSame([], $this->errors($this->valid(['time_start' => $start, 'time_end' => $end])), "jam $start-$end");
        }
    }

    public function test_jam_harus_keduanya_terisi_atau_keduanya_kosong(): void
    {
        $this->assertArrayHasKey('time_end', $this->errors($this->valid(['time_start' => '17:00', 'time_end' => null])));
        $this->assertArrayHasKey('time_start', $this->errors($this->valid(['time_start' => null, 'time_end' => '08:00'])));
    }

    public function test_jam_mulai_dan_selesai_tidak_boleh_sama(): void
    {
        $this->assertArrayHasKey('time_end', $this->errors($this->valid(['time_start' => '08:00', 'time_end' => '08:00'])));
        $this->assertArrayHasKey('time_end', $this->errors($this->valid(['time_start' => '08:00', 'time_end' => '08:00:00'])));
    }

    public function test_format_jam_tidak_valid(): void
    {
        foreach (['25:00', '8:00', '12:60', 'abc', '12', '12:00:60'] as $bad) {
            $this->assertArrayHasKey('time_start', $this->errors($this->valid(['time_start' => $bad, 'time_end' => '23:00'])), "jam $bad");
        }
    }

    public function test_prioritas_wajib_angka_dalam_rentang(): void
    {
        $this->assertArrayHasKey('priority', $this->errors($this->valid(['priority' => null])));

        foreach ([0, -5, 65536, 'abc', '1.5'] as $priority) {
            $this->assertArrayHasKey('priority', $this->errors($this->valid(['priority' => $priority])), "prioritas $priority");
        }

        $this->assertSame([], $this->errors($this->valid(['priority' => 1])));
        $this->assertSame([], $this->errors($this->valid(['priority' => 65535])));
    }

    public function test_prioritas_harus_unik(): void
    {
        $this->insertRule(10);

        $this->assertArrayHasKey('priority', $this->errors($this->valid(['priority' => 10])));
        $this->assertSame([], $this->errors($this->valid(['priority' => 11])));
    }

    public function test_ubah_rule_boleh_memakai_prioritas_miliknya_sendiri(): void
    {
        $id    = $this->insertRule(10);
        $other = $this->insertRule(20);

        // rule id $id tetap berprioritas 10: boleh
        $this->assertSame([], $this->errors($this->valid(['priority' => 10]), $id));

        // tetapi tidak boleh mengambil prioritas rule lain
        $this->assertArrayHasKey('priority', $this->errors($this->valid(['priority' => 20]), $id));
        $this->assertNotSame($id, $other);
    }

    public function test_normalisasi_nilai(): void
    {
        $validated = $this->validateRequest([
            'priority'    => 101,
            'src_ip'      => '   ',
            'dst_ip'      => '',
            'protocol'    => ' tcp ',
            'dst_port'    => '',
            'time_start'  => '',
            'time_end'    => '',
            'action'      => 'ALLOW',
            'description' => '',
            // 'enabled' sengaja tidak dikirim (checkbox tidak dicentang)
        ])->validated();

        $this->assertSame('TCP', $validated['protocol']);
        $this->assertSame('allow', $validated['action']);
        $this->assertNull($validated['src_ip']);
        $this->assertNull($validated['dst_ip']);
        $this->assertNull($validated['dst_port']);
        $this->assertNull($validated['time_start']);
        $this->assertNull($validated['time_end']);
        $this->assertNull($validated['description']);
        $this->assertFalse($validated['enabled']);

        $any = $this->validateRequest($this->valid(['protocol' => 'ANY', 'dst_port' => null]))->validated();
        $this->assertSame('any', $any['protocol']);
    }

    public function test_keterangan_maksimal_255_karakter(): void
    {
        $this->assertSame([], $this->errors($this->valid(['description' => str_repeat('a', 255)])));
        $this->assertArrayHasKey('description', $this->errors($this->valid(['description' => str_repeat('a', 256)])));
    }

    public function test_hasil_validasi_aman_dipakai_rule_matcher(): void
    {
        // Kontrak D1-D3: rule yang lolos validasi tidak boleh membuat RuleMatcher melempar exception.
        $windows = [[null, null], ['08:00', '17:00'], ['17:00', '08:00:00'], ['23:59', '00:00']];
        $traffic = [
            'timestamp' => '2026-11-20 22:41:37', 'src_ip' => '172.16.4.8', 'dst_ip' => '10.0.0.5',
            'protocol' => 'TCP', 'dst_port' => 22,
        ];

        foreach ($windows as $i => [$start, $end]) {
            $validated = $this->validateRequest($this->valid([
                'priority' => 200 + $i, 'time_start' => $start, 'time_end' => $end, 'src_ip' => null,
            ]))->validated();

            $this->assertIsBool(RuleMatcher::matches($traffic, $validated));
        }
    }
}
