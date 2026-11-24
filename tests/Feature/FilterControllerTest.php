<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PERHATIAN: RefreshDatabase mengosongkan database. Pastikan tes memakai database tes
 * (sqlite :memory: atau *_test). Pengaman ada di tests/TestCase.php.
 *
 * Halaman GET /filter belum diuji karena view-nya dibuat di D7.
 */
class FilterControllerTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'timestamp,src_ip,dst_ip,protocol,dst_port';

    private function operator(): User
    {
        $role = Role::firstOrCreate(['name' => Role::OPERATOR], ['description' => 'operator']);

        return User::create([
            'username' => 'operator_tes',
            'name'     => 'Operator',
            'password' => Hash::make('Passw0rd!x'),
            'role_id'  => $role->id,
        ]);
    }

    private function insertRule(int $priority, array $cond, string $action, bool $enabled = true): int
    {
        return DB::table('rules')->insertGetId(array_merge([
            'priority' => $priority, 'src_ip' => null, 'dst_ip' => null, 'protocol' => 'any',
            'dst_port' => null, 'time_start' => null, 'time_end' => null,
            'action' => $action, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now(),
        ], $cond));
    }

    /** 4 rule contoh bagian 4.1 laporan. */
    private function seedRules(): array
    {
        return [
            'allow'    => $this->insertRule(10, ['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443], 'allow'),
            'ssh'      => $this->insertRule(20, ['protocol' => 'TCP', 'dst_port' => 22, 'time_start' => '17:00:00', 'time_end' => '08:00:00'], 'deny'),
            'log'      => $this->insertRule(30, ['protocol' => 'ICMP'], 'log'),
            'catchall' => $this->insertRule(999, [], 'deny'),
        ];
    }

    private function csvFile(string ...$lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('trafik.csv', implode("\n", $lines)."\n");
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/filter/upload', ['csv' => $file]);
    }

    // ------------------------------------------------------------------ hak akses

    public function test_auditor_ditolak(): void
    {
        $role = Role::firstOrCreate(['name' => Role::AUDITOR], ['description' => 'auditor']);
        $auditor = User::create([
            'username' => 'auditor_tes', 'name' => 'Auditor',
            'password' => Hash::make('Passw0rd!x'), 'role_id' => $role->id,
        ]);

        $requests = [
            ['GET',  '/filter',        []],
            ['POST', '/filter/upload', ['csv' => $this->csvFile(self::HEADER)]],
            ['POST', '/filter/single', []],
        ];

        foreach ($requests as [$method, $uri, $data]) {
            $response = $method === 'GET'
                ? $this->actingAs($auditor)->get($uri)
                : $this->actingAs($auditor)->post($uri, $data);
            $status = $response->getStatusCode();

            $this->assertSame(403, $status, "$method $uri seharusnya 403 tetapi mengembalikan $status");
        }

        $this->assertDatabaseCount('traffic_logs', 0);
    }

    public function test_pengguna_belum_login_diarahkan_ke_login(): void
    {
        $this->post('/filter/upload', [])->assertRedirect(route('login'));
        $this->post('/filter/single', [])->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ unggah CSV

    public function test_unggah_csv_mengevaluasi_dan_menyimpan_keputusan_beserta_rule(): void
    {
        $ids = $this->seedRules();

        $response = $this->upload($this->operator(), $this->csvFile(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443',   // allow oleh rule 10
            '2026-11-20 22:41:37,172.16.4.8,10.0.0.5,TCP,22',      // deny oleh rule 20
            '2026-11-20 10:02:11,192.168.1.25,10.0.0.9,ICMP,',     // dicatat rule 30, deny oleh rule 999
            '2026-11-20 10:00:00,192.168.1.10,10.0.0.53,UDP,53'    // deny oleh rule 999
        ));

        $response->assertRedirect(route('filter.index'));
        $response->assertSessionHas('filter_result.total', 4);
        $response->assertSessionHas('filter_result.allow', 1);
        $response->assertSessionHas('filter_result.deny', 3);
        $response->assertSessionHas('filter_result.logged', 1);
        $response->assertSessionHas('filter_result.default_deny', 0);
        $response->assertSessionHas('filter_result.error_count', 0);

        $this->assertDatabaseCount('traffic_logs', 4);

        $this->assertDatabaseHas('traffic_logs', [
            'src_ip' => '192.168.1.10', 'dst_port' => 443, 'decision' => 'allow',
            'rule_id' => $ids['allow'], 'is_default' => 0, 'logged_rule_ids' => null,
        ]);
        $this->assertDatabaseHas('traffic_logs', [
            'src_ip' => '172.16.4.8', 'dst_port' => 22, 'decision' => 'deny', 'rule_id' => $ids['ssh'],
        ]);
        $this->assertDatabaseHas('traffic_logs', [
            'protocol' => 'ICMP', 'dst_port' => null, 'decision' => 'deny',
            'rule_id' => $ids['catchall'], 'logged_rule_ids' => '['.$ids['log'].']',
        ]);
    }

    public function test_baris_rusak_dilewati_dan_dilaporkan_baris_valid_tetap_disimpan(): void
    {
        $this->seedRules();

        $response = $this->upload($this->operator(), $this->csvFile(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443',   // baris 2 valid
            '2026-11-20 11:00:00,999.1.1.1,10.0.0.5,TCP,443',      // baris 3 rusak
            '2026-11-20 11:01:00,192.168.1.10,10.0.0.5,HTTP,443'   // baris 4 rusak
        ));

        $response->assertRedirect(route('filter.index'));
        $response->assertSessionHas('filter_result.total', 1);
        $response->assertSessionHas('filter_result.error_count', 2);
        $response->assertSessionHas('filter_result.errors.0.line', 3);
        $response->assertSessionHas('filter_result.errors.1.line', 4);
        $this->assertDatabaseCount('traffic_logs', 1);
    }

    public function test_semua_baris_rusak_tidak_menyimpan_apa_pun(): void
    {
        $this->seedRules();

        $response = $this->upload($this->operator(), $this->csvFile(
            self::HEADER,
            '2026-11-20 11:00:00,999.1.1.1,10.0.0.5,TCP,443',
            'kemarin sore,192.168.1.10,10.0.0.5,TCP,443'
        ));

        $response->assertSessionHas('error');
        $response->assertSessionHas('filter_result.error_count', 2);
        $this->assertDatabaseCount('traffic_logs', 0);
    }

    public function test_header_salah_ditolak_seluruhnya(): void
    {
        $this->seedRules();

        $response = $this->upload($this->operator(), $this->csvFile('a,b,c', '1,2,3'));

        $response->assertSessionHas('error', fn ($m) => str_contains($m, 'Header CSV tidak valid'));
        $response->assertSessionMissing('filter_result');
        $this->assertDatabaseCount('traffic_logs', 0);
    }

    public function test_validasi_unggahan(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator)->post('/filter/upload', [])->assertSessionHasErrors('csv');

        $this->actingAs($operator)
            ->post('/filter/upload', ['csv' => UploadedFile::fake()->create('trafik.pdf', 10)])
            ->assertSessionHasErrors('csv');

        $this->actingAs($operator)
            ->post('/filter/upload', ['csv' => UploadedFile::fake()->create('besar.csv', 6000)])   // 6000 KB > 5 MB
            ->assertSessionHasErrors('csv');

        $this->assertDatabaseCount('traffic_logs', 0);
    }

    // ------------------------------------------------------------------ perilaku evaluasi

    public function test_tanpa_rule_semua_trafik_default_deny(): void
    {
        $response = $this->upload($this->operator(), $this->csvFile(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443'
        ));

        $response->assertSessionHas('filter_result.default_deny', 1);
        $response->assertSessionHas('filter_result.active_rules', 0);
        $this->assertDatabaseHas('traffic_logs', ['decision' => 'deny', 'rule_id' => null, 'is_default' => 1]);
    }

    public function test_rule_nonaktif_tidak_dipakai(): void
    {
        $this->insertRule(10, ['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443], 'allow', false);
        $catchall = $this->insertRule(999, [], 'deny');

        $this->upload($this->operator(), $this->csvFile(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443'
        ));

        $this->assertDatabaseHas('traffic_logs', ['decision' => 'deny', 'rule_id' => $catchall, 'is_default' => 0]);
    }

    public function test_halaman_hasil_hanya_membawa_200_baris_pertama(): void
    {
        $this->seedRules();

        $lines = [self::HEADER];
        for ($i = 0; $i < 250; $i++) {
            $lines[] = '2026-11-20 09:15:02,192.168.1.'.(($i % 250) + 1).',10.0.0.5,TCP,443';
        }

        $response = $this->upload($this->operator(), $this->csvFile(...$lines));

        $response->assertSessionHas('filter_result.total', 250);
        $response->assertSessionHas('filter_result.rows_shown', 200);
        $this->assertDatabaseCount('traffic_logs', 250);
    }

    // ------------------------------------------------------------------ input satu trafik

    public function test_input_satu_trafik_valid_disimpan(): void
    {
        $ids = $this->seedRules();

        $response = $this->actingAs($this->operator())->post('/filter/single', [
            'timestamp' => '2026-11-20T09:15',        // format <input type="datetime-local">
            'src_ip'    => '192.168.1.10',
            'dst_ip'    => '10.0.0.5',
            'protocol'  => 'tcp',
            'dst_port'  => '443',
        ]);

        $response->assertRedirect(route('filter.index'));
        $response->assertSessionHas('filter_result.source', 'single');
        $response->assertSessionHas('filter_result.allow', 1);
        $this->assertDatabaseHas('traffic_logs', [
            'timestamp' => '2026-11-20 09:15:00', 'protocol' => 'TCP', 'decision' => 'allow', 'rule_id' => $ids['allow'],
        ]);
    }

    public function test_input_satu_trafik_tidak_valid_ditolak(): void
    {
        $this->seedRules();

        $response = $this->actingAs($this->operator())->post('/filter/single', [
            'timestamp' => 'kemarin', 'src_ip' => '999.1.1.1', 'dst_ip' => '10.0.0.5',
            'protocol' => 'TCP', 'dst_port' => '443',
        ]);

        $response->assertSessionHasErrors('traffic');
        $this->assertDatabaseCount('traffic_logs', 0);
    }

    // ------------------------------------------------------------------ kinerja (bagian dari D8)

    public function test_kinerja_10000_baris_kurang_dari_5_detik(): void
    {
        $this->seedRules();
        $operator = $this->operator();

        $lines = [self::HEADER];
        for ($i = 0; $i < 10000; $i++) {
            $icmp    = $i % 3 === 0;
            $lines[] = sprintf(
                '2026-11-20 %02d:%02d:%02d,192.168.1.%d,10.0.0.5,%s,%s',
                $i % 24, $i % 60, $i % 60, ($i % 250) + 1,
                $icmp ? 'ICMP' : 'TCP',
                $icmp ? '' : [22, 443, 80][$i % 3]
            );
        }
        $file = $this->csvFile(...$lines);

        $start   = microtime(true);
        $this->actingAs($operator)->post('/filter/upload', ['csv' => $file])->assertRedirect(route('filter.index'));
        $seconds = microtime(true) - $start;

        $this->assertDatabaseCount('traffic_logs', 10000);
        $this->assertLessThan(5.0, $seconds, "10.000 baris memakan {$seconds} detik");
    }
}