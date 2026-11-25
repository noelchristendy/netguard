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
 * Memastikan halaman D7 benar-benar bisa dirender (sintaks Blade, variabel, rute) dan menampilkan
 * data yang benar. Perilaku simpan/ubah/hapus diuji di RuleControllerTest dan FilterControllerTest.
 */
class RuleFilterPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::create([
            'username' => $role.'_tes',
            'name'     => ucfirst($role),
            'password' => Hash::make('Passw0rd!x'),
            'role_id'  => $roleModel->id,
        ]);
    }

    private function insertRule(int $priority, array $overrides = []): int
    {
        return DB::table('rules')->insertGetId(array_merge([
            'priority' => $priority, 'src_ip' => null, 'dst_ip' => null, 'protocol' => 'any',
            'dst_port' => null, 'time_start' => null, 'time_end' => null,
            'action' => 'deny', 'enabled' => true, 'description' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------------ Manajemen Rule

    public function test_daftar_rule_tampil_untuk_administrator_dan_operator_berurutan_menurut_prioritas(): void
    {
        $this->insertRule(30, ['description' => 'rule-ketiga']);
        $this->insertRule(10, ['description' => 'rule-pertama']);
        $this->insertRule(20, ['description' => 'rule-kedua']);

        foreach ([Role::ADMINISTRATOR, Role::OPERATOR] as $role) {
            $this->actingAs($this->user($role))
                ->get('/rules')
                ->assertOk()
                ->assertSee('Tambah Rule')
                ->assertSeeInOrder(['rule-pertama', 'rule-kedua', 'rule-ketiga']);
        }
    }

    public function test_daftar_rule_kosong(): void
    {
        $this->actingAs($this->user(Role::OPERATOR))
            ->get('/rules')
            ->assertOk()
            ->assertSee('Belum ada rule');
    }

    public function test_ringkasan_kondisi_dan_jendela_waktu_tengah_malam(): void
    {
        $this->insertRule(20, [
            'protocol' => 'TCP', 'dst_port' => 22, 'src_ip' => '172.16.4.0/24',
            'time_start' => '17:00:00', 'time_end' => '08:00:00',
        ]);

        $this->actingAs($this->user(Role::OPERATOR))
            ->get('/rules')
            ->assertOk()
            ->assertSee('Sumber 172.16.4.0/24')
            ->assertSee('Protokol TCP')
            ->assertSee('Port 22')
            ->assertSee('Jam 17:00 - 08:00')
            ->assertSee('melewati tengah malam');
    }

    public function test_peringatan_hanya_untuk_rule_allow_aktif_tanpa_kondisi(): void
    {
        $operator = $this->user(Role::OPERATOR);

        // deny tanpa kondisi (rule penutup) bukan masalah
        $this->insertRule(999, ['action' => 'deny']);
        $this->actingAs($operator)->get('/rules')->assertOk()->assertDontSee('membuka seluruh trafik');

        // allow tanpa kondisi tetapi nonaktif juga bukan masalah
        $this->insertRule(50, ['action' => 'allow', 'enabled' => false]);
        $this->actingAs($operator)->get('/rules')->assertOk()->assertDontSee('membuka seluruh trafik');

        // allow tanpa kondisi dan aktif: beri peringatan
        $this->insertRule(60, ['action' => 'allow']);
        $this->actingAs($operator)->get('/rules')->assertOk()->assertSee('membuka seluruh trafik');
    }

    public function test_tombol_naik_turun_tidak_muncul_di_ujung_dan_aksi_tersedia(): void
    {
        $a = $this->insertRule(10);
        $b = $this->insertRule(20);

        $html = $this->actingAs($this->user(Role::OPERATOR))->get('/rules')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'title="Naikkan prioritas"'));   // baris pertama tidak punya tombol naik
        $this->assertSame(1, substr_count($html, 'title="Turunkan prioritas"'));  // baris terakhir tidak punya tombol turun
        $this->assertStringContainsString(route('rules.toggle', $a), $html);
        $this->assertStringContainsString(route('rules.edit', $b), $html);
        $this->assertStringContainsString(route('rules.destroy', $b), $html);
    }

    public function test_form_tambah_rule(): void
    {
        $response = $this->actingAs($this->user(Role::OPERATOR))->get('/rules/create')->assertOk();

        foreach (['priority', 'src_ip', 'dst_ip', 'protocol', 'dst_port', 'time_start', 'time_end', 'action', 'description', 'enabled'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('action="'.route('rules.store').'"', false);
        $response->assertDontSee('name="_method"', false);        // tambah memakai POST biasa
        $response->assertSee('name="enabled" value="0"', false);  // nilai 0 selalu terkirim (checkbox tidak dicentang)
    }

    public function test_form_ubah_rule_terisi_dengan_nilai_tersimpan(): void
    {
        $id = $this->insertRule(20, [
            'src_ip' => '172.16.4.0/24', 'protocol' => 'TCP', 'dst_port' => 22,
            'time_start' => '17:00:00', 'time_end' => '08:00:00', 'description' => 'SSH luar jam kerja',
        ]);

        $this->actingAs($this->user(Role::OPERATOR))
            ->get("/rules/{$id}/edit")
            ->assertOk()
            ->assertSee('action="'.route('rules.update', $id).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="172.16.4.0/24"', false)
            ->assertSee('value="22"', false)
            ->assertSee('value="17:00"', false)    // jam ditampilkan tanpa detik
            ->assertSee('value="08:00"', false)
            ->assertSee('value="SSH luar jam kerja"', false);
    }

    // ------------------------------------------------------------------ Penyaringan

    public function test_halaman_penyaringan_tanpa_hasil(): void
    {
        $this->actingAs($this->user(Role::OPERATOR))
            ->get('/filter')
            ->assertOk()
            ->assertSee('Unggah CSV')
            ->assertSee('Input Satu Trafik')
            ->assertSee('name="csv"', false)
            ->assertSee('Tidak ada rule aktif')
            ->assertDontSee('Hasil Penyaringan');
    }

    public function test_halaman_penyaringan_menyebut_jumlah_rule_aktif(): void
    {
        $this->insertRule(10);
        $this->insertRule(20, ['enabled' => false]);

        $this->actingAs($this->user(Role::OPERATOR))
            ->get('/filter')
            ->assertOk()
            ->assertSee('1 rule aktif')
            ->assertDontSee('Tidak ada rule aktif');
    }

    public function test_halaman_penyaringan_menampilkan_hasil_dan_meng_escape_teks_pengguna(): void
    {
        $result = [
            'total' => 2, 'allow' => 1, 'deny' => 1, 'default_deny' => 1, 'logged' => 1, 'active_rules' => 1,
            'rows' => [
                ['timestamp' => '2026-11-20 09:15:02', 'src_ip' => '192.168.1.10', 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP',
                 'dst_port' => 443, 'decision' => 'allow', 'is_default' => false, 'rule' => 'Prioritas 10', 'logged' => []],
                ['timestamp' => '2026-11-20 10:02:11', 'src_ip' => '192.168.1.25', 'dst_ip' => '10.0.0.9', 'protocol' => 'ICMP',
                 'dst_port' => null, 'decision' => 'deny', 'is_default' => true, 'rule' => null, 'logged' => ['Prioritas 30']],
            ],
            'rows_shown' => 2, 'source' => 'csv', 'filename' => 'trafik-uji.csv',
            'errors' => [['line' => 3, 'message' => 'IP sumber tidak valid: <script>alert(1)</script>']],
            'error_count' => 1, 'truncated' => false,
        ];

        $response = $this->actingAs($this->user(Role::OPERATOR))
            ->withSession(['filter_result' => $result])
            ->get('/filter')
            ->assertOk()
            ->assertSee('Hasil Penyaringan')
            ->assertSee('trafik-uji.csv')
            ->assertSee('192.168.1.10')
            ->assertSee('Prioritas 10')
            ->assertSee('default deny')
            ->assertSee('Prioritas 30')
            ->assertSee('1 baris dilewati karena tidak valid')
            ->assertSee('IP sumber tidak valid');

        // Teks dari berkas pengguna harus ter-escape, tidak boleh menjadi HTML
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_alur_unggah_csv_lalu_hasil_tampil_di_halaman(): void
    {
        $this->insertRule(10, ['src_ip' => '192.168.1.0/24', 'protocol' => 'TCP', 'dst_port' => 443, 'action' => 'allow']);
        $this->insertRule(999, ['action' => 'deny']);

        $csv = "timestamp,src_ip,dst_ip,protocol,dst_port\n"
            ."2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443\n"
            ."2026-11-20 11:00:00,999.1.1.1,10.0.0.5,TCP,443\n";

        $this->actingAs($this->user(Role::OPERATOR))
            ->followingRedirects()
            ->post('/filter/upload', ['csv' => UploadedFile::fake()->createWithContent('trafik.csv', $csv)])
            ->assertOk()
            ->assertSee('Hasil Penyaringan')
            ->assertSee('Prioritas 10')
            ->assertSee('1 baris dilewati karena tidak valid')
            ->assertSee('IP sumber tidak valid');
    }
}
