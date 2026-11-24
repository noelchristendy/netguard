<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PERHATIAN: RefreshDatabase mengosongkan database. Pastikan phpunit.xml memakai database
 * tes terpisah (netguard_test), bukan netguard.
 *
 * Tes ini sengaja tidak memuat halaman (GET index/create/edit) karena view dibuat di D7.
 * Yang diuji: perilaku POST/PUT/PATCH/DELETE, hak akses, dan pencatatan audit.
 */
class RuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::create([
            'username' => $role.'_tes',
            'name'     => ucfirst($role),
            'password' => Hash::make('Passw0rd!x'),
            'role_id'  => $roleModel->id,
        ]);
    }

    private function payload(array $overrides = []): array
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
            'enabled'     => 1,
            'description' => 'HTTPS dari LAN',
        ], $overrides);
    }

    private function ruleRow(int $priority, array $overrides = []): int
    {
        return DB::table('rules')->insertGetId(array_merge([
            'priority'    => $priority,
            'src_ip'      => '192.168.1.0/24',
            'protocol'    => 'TCP',
            'dst_port'    => 443,
            'action'      => 'allow',
            'enabled'     => true,
            'description' => 'HTTPS dari LAN',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
    }

    private function lastAudit(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    // ------------------------------------------------------------------ hak akses

    public function test_auditor_ditolak_403_dan_tercatat(): void
    {
        $auditor = $this->userWithRole(Role::AUDITOR);

        $this->actingAs($auditor)->get('/rules')->assertForbidden();
        $this->actingAs($auditor)->post('/rules', $this->payload())->assertForbidden();

        $this->assertDatabaseCount('rules', 0);
        $this->assertSame(2, AuditLog::where('action', 'ACCESS_DENIED')->where('user_id', $auditor->id)->count());
    }

    public function test_pengguna_belum_login_diarahkan_ke_login(): void
    {
        $this->post('/rules', $this->payload())->assertRedirect(route('login'));

        $this->assertDatabaseCount('rules', 0);
    }

    // ------------------------------------------------------------------ tambah

    public function test_administrator_dan_operator_boleh_membuat_rule(): void
    {
        foreach ([Role::ADMINISTRATOR => 100, Role::OPERATOR => 200] as $role => $priority) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post('/rules', $this->payload(['priority' => $priority]))
                ->assertRedirect(route('rules.index'));

            $this->assertDatabaseHas('rules', ['priority' => $priority, 'created_by' => $user->id, 'action' => 'allow']);
            $this->assertNotNull(AuditLog::where('action', 'RULE_CREATED')->where('user_id', $user->id)->first(), "audit $role");
        }
    }

    public function test_rule_tidak_valid_ditolak_dan_tidak_tercatat(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);

        $this->actingAs($operator)
            ->post('/rules', $this->payload(['src_ip' => '999.1.1.1']))
            ->assertSessionHasErrors('src_ip');

        $this->assertDatabaseCount('rules', 0);
        $this->assertNull($this->lastAudit('RULE_CREATED'));
    }

    // ------------------------------------------------------------------ ubah dan hapus (U8)

    public function test_u8_ubah_rule_tercatat_dengan_pelaku_dan_waktu(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $id = $this->ruleRow(50);

        $this->actingAs($operator)
            ->put("/rules/{$id}", $this->payload(['priority' => 50, 'action' => 'deny', 'dst_port' => 80]))
            ->assertRedirect(route('rules.index'));

        $log = $this->lastAudit('RULE_UPDATED');

        $this->assertNotNull($log);
        $this->assertSame($operator->id, $log->user_id);
        $this->assertTrue($log->timestamp->between(now()->subMinute(), now()->addMinute()));

        $detail = json_decode($log->detail, true);
        $this->assertSame($id, $detail['rule_id']);
        $this->assertSame('allow', $detail['lama']['action']);
        $this->assertSame('deny', $detail['baru']['action']);
        $this->assertEquals(443, $detail['lama']['dst_port']);
        $this->assertEquals(80, $detail['baru']['dst_port']);
        $this->assertArrayNotHasKey('priority', $detail['baru']);   // yang tidak berubah tidak dicatat
    }

    public function test_ubah_tanpa_perubahan_nyata_tidak_mencatat_audit(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $id = $this->ruleRow(50);

        $this->actingAs($operator)
            ->put("/rules/{$id}", $this->payload(['priority' => 50]))
            ->assertRedirect(route('rules.index'));

        $this->assertNull($this->lastAudit('RULE_UPDATED'));
    }

    public function test_ubah_ke_prioritas_rule_lain_ditolak(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $a = $this->ruleRow(10);
        $this->ruleRow(20);

        $this->actingAs($operator)
            ->put("/rules/{$a}", $this->payload(['priority' => 20]))
            ->assertSessionHasErrors('priority');

        $this->assertSame(10, (int) Rule::find($a)->priority);
    }

    public function test_u8_hapus_rule_tercatat_dengan_data_lama(): void
    {
        $admin = $this->userWithRole(Role::ADMINISTRATOR);
        $id = $this->ruleRow(77);

        $this->actingAs($admin)->delete("/rules/{$id}")->assertRedirect(route('rules.index'));

        $this->assertDatabaseMissing('rules', ['id' => $id]);

        $log = $this->lastAudit('RULE_DELETED');
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);

        $detail = json_decode($log->detail, true);
        $this->assertSame($id, $detail['rule_id']);
        $this->assertEquals(77, $detail['lama']['priority']);
        $this->assertSame('allow', $detail['lama']['action']);
    }

    // ------------------------------------------------------------------ toggle dan urutan

    public function test_toggle_membalik_status_dan_tercatat(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $id = $this->ruleRow(10);

        $this->actingAs($operator)->patch("/rules/{$id}/toggle")->assertRedirect();

        $this->assertFalse((bool) Rule::find($id)->enabled);

        $detail = json_decode($this->lastAudit('RULE_TOGGLED')->detail, true);
        $this->assertTrue($detail['lama']['enabled']);
        $this->assertFalse($detail['baru']['enabled']);

        $this->actingAs($operator)->patch("/rules/{$id}/toggle");
        $this->assertTrue((bool) Rule::find($id)->enabled);
    }

    public function test_pindah_urutan_menukar_prioritas_dengan_tetangga(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $a = $this->ruleRow(10);
        $b = $this->ruleRow(20);
        $c = $this->ruleRow(30);

        $this->actingAs($operator)->post("/rules/{$b}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(20, (int) Rule::find($a)->priority);
        $this->assertSame(10, (int) Rule::find($b)->priority);
        $this->assertSame(30, (int) Rule::find($c)->priority);
        $this->assertSame(2, AuditLog::where('action', 'RULE_UPDATED')->count());

        $this->actingAs($operator)->post("/rules/{$b}/move", ['direction' => 'down']);

        $this->assertSame(10, (int) Rule::find($a)->priority);
        $this->assertSame(20, (int) Rule::find($b)->priority);
    }

    public function test_pindah_urutan_di_ujung_tidak_mengubah_apa_pun(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $a = $this->ruleRow(10);
        $b = $this->ruleRow(20);

        $this->actingAs($operator)->post("/rules/{$a}/move", ['direction' => 'up'])->assertSessionHas('error');
        $this->actingAs($operator)->post("/rules/{$b}/move", ['direction' => 'down'])->assertSessionHas('error');

        $this->assertSame(10, (int) Rule::find($a)->priority);
        $this->assertSame(20, (int) Rule::find($b)->priority);
        $this->assertNull($this->lastAudit('RULE_UPDATED'));
    }

    public function test_arah_pindah_tidak_valid_ditolak(): void
    {
        $operator = $this->userWithRole(Role::OPERATOR);
        $a = $this->ruleRow(10);

        $this->actingAs($operator)
            ->post("/rules/{$a}/move", ['direction' => 'left'])
            ->assertSessionHasErrors('direction');
    }
}
