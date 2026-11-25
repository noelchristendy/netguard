<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRuleRequest;
use App\Models\Rule;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manajemen rule. Rute dilindungi middleware role:administrator,operator (Role::RULE_MANAGERS).
 * Setiap perubahan dicatat ke audit dengan pelaku dan nilai lama/baru.
 */
class RuleController extends Controller
{
    /** Kolom yang dicatat di audit. */
    private const TRACKED = [
        'priority', 'src_ip', 'dst_ip', 'protocol', 'dst_port',
        'time_start', 'time_end', 'action', 'enabled', 'description',
    ];

    public function index(): View
    {
        return view('rules.index', [
            'rules' => Rule::with('creator')->orderBy('priority')->get(),
        ]);
    }

    public function create(): View
    {
        return view('rules.form', [
            'rule' => new Rule(['protocol' => 'any', 'action' => 'allow', 'enabled' => true]),
        ]);
    }

    public function store(StoreRuleRequest $request): RedirectResponse
    {
        $rule = new Rule($request->validated());
        $rule->created_by = Auth::id();
        $rule->save();
        $rule->refresh();   // nilai seperti yang tersimpan di database (mis. jam menjadi HH:MM:SS)

        AuditService::log(Auth::id(), AuditService::RULE_CREATED, [
            'rule_id' => $rule->id,
            'baru'    => $rule->only(self::TRACKED),
        ]);

        return redirect()->route('rules.index')
            ->with('success', "Rule prioritas {$rule->priority} berhasil dibuat.");
    }

    public function edit(Rule $rule): View
    {
        return view('rules.form', ['rule' => $rule]);
    }

    public function update(StoreRuleRequest $request, Rule $rule): RedirectResponse
    {
        $before = $rule->only(self::TRACKED);

        $rule->update($request->validated());
        $rule->refresh();

        $after = $rule->only(self::TRACKED);
        $lama  = [];
        $baru  = [];

        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                $lama[$key] = $before[$key];
                $baru[$key] = $value;
            }
        }

        // Tanpa perubahan nyata: tidak ada yang perlu dicatat
        if ($lama === []) {
            return redirect()->route('rules.index')->with('success', 'Tidak ada perubahan pada rule.');
        }

        AuditService::log(Auth::id(), AuditService::RULE_UPDATED, [
            'rule_id' => $rule->id,
            'lama'    => $lama,
            'baru'    => $baru,
        ]);

        return redirect()->route('rules.index')
            ->with('success', "Rule prioritas {$rule->priority} berhasil diperbarui.");
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        // Simpan data SEBELUM dihapus. Setelah itu datanya hilang.
        $id       = $rule->id;
        $snapshot = $rule->only(self::TRACKED);

        $rule->delete();

        AuditService::log(Auth::id(), AuditService::RULE_DELETED, [
            'rule_id' => $id,
            'lama'    => $snapshot,
        ]);

        return redirect()->route('rules.index')
            ->with('success', "Rule prioritas {$snapshot['priority']} dihapus.");
    }

    public function toggle(Rule $rule): RedirectResponse
    {
        $lama = (bool) $rule->enabled;

        $rule->update(['enabled' => ! $lama]);

        AuditService::log(Auth::id(), AuditService::RULE_TOGGLED, [
            'rule_id'  => $rule->id,
            'priority' => $rule->priority,
            'lama'     => ['enabled' => $lama],
            'baru'     => ['enabled' => ! $lama],
        ]);

        return back()->with('success', 'Rule prioritas '.$rule->priority.($lama ? ' dinonaktifkan.' : ' diaktifkan.'));
    }

    /**
     * Menukar prioritas rule dengan tetangganya (direction: up atau down).
     * Kolom priority bersifat unik, jadi penukaran lewat nilai sementara di dalam transaksi.
     */
    public function move(Request $request, Rule $rule): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $result = DB::transaction(function () use ($rule, $direction) {
            $current = Rule::lockForUpdate()->findOrFail($rule->id);

            $neighbor = $direction === 'up'
                ? Rule::where('priority', '<', $current->priority)->orderByDesc('priority')->lockForUpdate()->first()
                : Rule::where('priority', '>', $current->priority)->orderBy('priority')->lockForUpdate()->first();

            if ($neighbor === null) {
                return null;
            }

            $a    = $current->priority;
            $b    = $neighbor->priority;
            $temp = (int) Rule::max('priority') + 1;   // nilai sementara agar keunikan tidak dilanggar

            $current->update(['priority' => $temp]);
            $neighbor->update(['priority' => $a]);
            $current->update(['priority' => $b]);

            return [$current, $neighbor, $a, $b];
        });

        if ($result === null) {
            return back()->with('error', $direction === 'up'
                ? 'Rule sudah berada di urutan paling atas.'
                : 'Rule sudah berada di urutan paling bawah.');
        }

        [$current, $neighbor, $a, $b] = $result;

        AuditService::log(Auth::id(), AuditService::RULE_UPDATED, [
            'rule_id'        => $current->id,
            'alasan'         => 'urutan ditukar',
            'lama'           => ['priority' => $a],
            'baru'           => ['priority' => $b],
            'ditukar_dengan' => $neighbor->id,
        ]);
        AuditService::log(Auth::id(), AuditService::RULE_UPDATED, [
            'rule_id'        => $neighbor->id,
            'alasan'         => 'urutan ditukar',
            'lama'           => ['priority' => $b],
            'baru'           => ['priority' => $a],
            'ditukar_dengan' => $current->id,
        ]);

        return back()->with('success', 'Urutan rule diperbarui.');
    }
}
