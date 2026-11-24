<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use App\Services\FilterService;
use App\Services\TrafficParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Penyaringan trafik: unggah CSV atau input satu trafik, evaluasi terhadap rule aktif,
 * simpan keputusan ke traffic_logs. Rute dilindungi middleware Role::RULE_MANAGERS.
 *
 * Setelah diproses, hasil dikirim lewat flash session ('filter_result') dan pengguna dialihkan
 * ke halaman /filter (pola Post-Redirect-Get), sehingga memuat ulang halaman tidak mengirim ulang
 * berkas. Yang dititipkan di session hanya ringkasan dan 200 baris pertama, bukan seluruh hasil.
 */
class FilterController extends Controller
{
    private const PREVIEW_ROWS = 200;   // baris yang ditampilkan di halaman hasil
    private const CHUNK        = 1000;  // ukuran insert batch ke traffic_logs

    private const RULE_COLUMNS = [
        'id', 'priority', 'src_ip', 'dst_ip', 'protocol', 'dst_port',
        'time_start', 'time_end', 'action', 'enabled',
    ];

    public function index(): View
    {
        return view('filter.index', [
            'result'      => session('filter_result'),
            'activeRules' => Rule::where('enabled', true)->count(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ], [
            'csv.required'   => 'Pilih berkas CSV terlebih dahulu.',
            'csv.file'       => 'Unggahan harus berupa berkas.',
            'csv.extensions' => 'Berkas harus berekstensi .csv atau .txt.',
            'csv.max'        => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $file   = $request->file('csv');
        $parsed = TrafficParser::parseFile($file->getRealPath());

        // Berkas ditolak seluruhnya (header salah, kosong, UTF-16)
        if ($parsed['fatal'] !== null) {
            return redirect()->route('filter.index')->with('error', $parsed['fatal']);
        }

        // Tidak ada satu pun baris valid: tampilkan galatnya, jangan simpan apa pun
        if ($parsed['rows'] === []) {
            return redirect()->route('filter.index')
                ->with('error', 'Tidak ada baris valid yang dapat diproses.')
                ->with('filter_result', $this->emptyResult($parsed, $file->getClientOriginalName()));
        }

        $result = $this->evaluateAndStore($parsed['rows']);
        $result += [
            'source'      => 'csv',
            'filename'    => $file->getClientOriginalName(),
            'errors'      => $parsed['errors'],
            'error_count' => $parsed['error_count'],
            'truncated'   => $parsed['truncated'],
        ];

        $message = "{$result['total']} trafik dievaluasi: {$result['allow']} allow, {$result['deny']} deny.";
        if ($parsed['error_count'] > 0) {
            $message .= " {$parsed['error_count']} baris dilewati karena tidak valid.";
        }
        if ($parsed['truncated']) {
            $message .= ' Berkas melebihi '.number_format(TrafficParser::MAX_ROWS, 0, ',', '.').' baris, sisanya tidak diproses.';
        }

        return redirect()->route('filter.index')
            ->with('success', $message)
            ->with('filter_result', $result);
    }

    public function single(Request $request): RedirectResponse
    {
        $parsed = TrafficParser::parseRow($request->only(TrafficParser::COLUMNS));

        if ($parsed['data'] === null) {
            return redirect()->route('filter.index')
                ->withInput()
                ->withErrors(['traffic' => $parsed['errors']]);
        }

        $result = $this->evaluateAndStore([$parsed['data']]);
        $result += [
            'source'      => 'single',
            'filename'    => null,
            'errors'      => [],
            'error_count' => 0,
            'truncated'   => false,
        ];

        return redirect()->route('filter.index')
            ->with('success', 'Trafik dievaluasi: '.($result['allow'] === 1 ? 'allow' : 'deny').'.')
            ->with('filter_result', $result);
    }

    // ------------------------------------------------------------------ inti

    /**
     * Rule dimuat SEKALI untuk seluruh trafik, bukan per baris.
     * Rule diubah ke array biasa: mengakses atribut model Eloquent jutaan kali jauh lebih lambat.
     *
     * @param array<int, array> $rows baris trafik valid dari TrafficParser
     */
    private function evaluateAndStore(array $rows): array
    {
        $rules      = Rule::all()->map(fn (Rule $rule) => $rule->only(self::RULE_COLUMNS));
        $priorities = $rules->pluck('priority', 'id');
        $activeRules = $rules->where('enabled', true)->count();

        $results = FilterService::evaluateMany($rows, $rules);

        $now     = now()->toDateTimeString();
        $insert  = [];
        $preview = [];
        $counts  = ['allow' => 0, 'deny' => 0, 'default_deny' => 0, 'logged' => 0];

        foreach ($rows as $i => $row) {
            $res = $results[$i];

            // Urutan kunci harus sama di setiap baris agar bisa di-insert sekaligus
            $insert[] = $row + [
                'decision'        => $res['decision'],
                'rule_id'         => $res['rule_id'],
                'is_default'      => $res['is_default'],
                'logged_rule_ids' => FilterService::encodeLoggedIds($res['logged_rule_ids']),   // null bila kosong
                'created_at'      => $now,
            ];

            $counts[$res['decision']]++;
            if ($res['is_default']) {
                $counts['default_deny']++;
            }
            if ($res['logged_rule_ids'] !== []) {
                $counts['logged']++;
            }

            if ($i < self::PREVIEW_ROWS) {
                $preview[] = $row + [
                    'decision'   => $res['decision'],
                    'is_default' => $res['is_default'],
                    'rule'       => $res['is_default'] ? null : $this->label($priorities, $res['rule_id']),
                    'logged'     => array_map(fn ($id) => $this->label($priorities, $id), $res['logged_rule_ids']),
                ];
            }
        }

        // Semua atau tidak sama sekali: gagal di tengah tidak meninggalkan data setengah jadi
        DB::transaction(function () use ($insert) {
            foreach (array_chunk($insert, self::CHUNK) as $chunk) {
                DB::table('traffic_logs')->insert($chunk);
            }
        });

        return [
            'total'        => count($rows),
            'allow'        => $counts['allow'],
            'deny'         => $counts['deny'],
            'default_deny' => $counts['default_deny'],
            'logged'       => $counts['logged'],
            'active_rules' => $activeRules,
            'rows'         => $preview,
            'rows_shown'   => count($preview),
        ];
    }

    private function label($priorities, $ruleId): string
    {
        return 'Prioritas '.($priorities[$ruleId] ?? '?');
    }

    private function emptyResult(array $parsed, string $filename): array
    {
        return [
            'total' => 0, 'allow' => 0, 'deny' => 0, 'default_deny' => 0, 'logged' => 0,
            'active_rules' => null, 'rows' => [], 'rows_shown' => 0,
            'source'      => 'csv',
            'filename'    => $filename,
            'errors'      => $parsed['errors'],
            'error_count' => $parsed['error_count'],
            'truncated'   => $parsed['truncated'],
        ];
    }
}
