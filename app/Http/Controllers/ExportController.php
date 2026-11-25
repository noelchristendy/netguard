<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    private const PDF_LIMIT = 500;

    /**
     * Ekspor log keputusan dalam CSV atau PDF.
     */
    public function decisions(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'ip' => ['nullable', 'ip'],
            'decision' => ['nullable', Rule::in(['allow', 'deny'])],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);

        $format = $filters['format'];
        unset($filters['format']);

        return $format === 'csv'
            ? $this->decisionsCsv($filters)
            : $this->decisionsPdf($filters);
    }

    /**
     * Ekspor log audit dalam CSV atau PDF.
     */
    public function audits(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'action' => ['nullable', Rule::in(AuditService::ALL)],
            'actor' => ['nullable', 'string', 'max:100'],
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);

        $format = $filters['format'];
        unset($filters['format']);

        return $format === 'csv'
            ? $this->auditsCsv($filters)
            : $this->auditsPdf($filters);
    }

    /**
     * Query log keputusan dengan filter yang sama seperti N3.
     */
    private function decisionQuery(array $filters): Builder
    {
        $query = DB::table('traffic_logs as tl')
            ->leftJoin('rules as r', 'tl.rule_id', '=', 'r.id')
            ->select([
                'tl.id',
                'tl.timestamp',
                'tl.src_ip',
                'tl.dst_ip',
                'tl.protocol',
                'tl.dst_port',
                'tl.decision',
                'tl.rule_id',
                'tl.is_default',
                'r.priority as rule_priority',
                'r.description as rule_description',
            ]);

        $this->applyDateFilters($query, 'tl.timestamp', $filters);

        $ip = trim((string) ($filters['ip'] ?? ''));

        if ($ip !== '') {
            $query->where(function (Builder $q) use ($ip) {
                $q->where('tl.src_ip', $ip)
                    ->orWhere('tl.dst_ip', $ip);
            });
        }

        if (!empty($filters['decision'])) {
            $query->where('tl.decision', $filters['decision']);
        }

        return $query
            ->orderByDesc('tl.timestamp')
            ->orderByDesc('tl.id');
    }

    /**
     * Query log audit dengan filter yang sama seperti N3.
     */
    private function auditQuery(array $filters): Builder
    {
        $query = DB::table('audit_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->select([
                'al.id',
                'al.timestamp',
                'al.user_id',
                'al.action',
                'al.detail',
                'u.username',
                'u.name as actor_name',
            ]);

        $this->applyDateFilters($query, 'al.timestamp', $filters);

        if (!empty($filters['action'])) {
            $query->where('al.action', $filters['action']);
        }

        $actor = trim((string) ($filters['actor'] ?? ''));

        if ($actor !== '') {
            $query->where(function (Builder $q) use ($actor) {
                $q->where('u.username', 'like', "%{$actor}%")
                    ->orWhere('u.name', 'like', "%{$actor}%");
            });
        }

        return $query
            ->orderByDesc('al.timestamp')
            ->orderByDesc('al.id');
    }

    /**
     * Filter tanggal awal dan akhir.
     */
    private function applyDateFilters(
        Builder $query,
        string $column,
        array $filters
    ): void {
        if (!empty($filters['date_from'])) {
            $start = Carbon::parse(
                $filters['date_from'],
                config('app.timezone')
            )->startOfDay();

            $query->where($column, '>=', $start);
        }

        if (!empty($filters['date_to'])) {
            $end = Carbon::parse(
                $filters['date_to'],
                config('app.timezone')
            )->endOfDay();

            $query->where($column, '<=', $end);
        }
    }

    /**
     * Mencegah CSV Injection.
     */
    private function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        if (preg_match('/^[\s]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Ekspor CSV log keputusan menggunakan streamDownload.
     */
    private function decisionsCsv(array $filters)
    {
        $query = $this->decisionQuery($filters);

        return response()->streamDownload(function () use ($query, $filters) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new \RuntimeException('Gagal membuat CSV.');
            }

            $exported = 0;

            try {
                // BOM agar karakter UTF-8 terbaca dengan baik di Excel.
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    'Waktu',
                    'IP Sumber',
                    'IP Tujuan',
                    'Protokol',
                    'Port',
                    'Keputusan',
                    'Rule Penentu',
                    'Keterangan Rule',
                ]);

                foreach ($query->cursor() as $row) {
                    if ($row->rule_id === null) {
                        $ruleName = $row->is_default
                            ? 'Default Deny'
                            : 'Rule Terhapus';
                    } else {
                        $ruleName = 'Rule #' . $row->rule_priority;
                    }

                    $cells = [
                        $row->timestamp,
                        $row->src_ip,
                        $row->dst_ip,
                        $row->protocol,
                        $row->dst_port,
                        strtoupper($row->decision),
                        $ruleName,
                        $row->rule_description,
                    ];

                    fputcsv(
                        $handle,
                        array_map([$this, 'safeCsvCell'], $cells),
                        ',',
                        '"',
                        '\\'
                    );

                    $exported++;
                }
            } finally {
                fclose($handle);
            }

            AuditService::log(
                Auth::id(),
                AuditService::REPORT_EXPORTED,
                [
                    'jenis' => 'decisions',
                    'format' => 'csv',
                    'filter' => $filters,
                    'jumlah_data' => $exported,
                ]
            );
        }, 'netguard-log-keputusan.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ekspor CSV log audit menggunakan streamDownload.
     */
    private function auditsCsv(array $filters)
    {
        $query = $this->auditQuery($filters);

        return response()->streamDownload(function () use ($query, $filters) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new \RuntimeException('Gagal membuat CSV.');
            }

            $exported = 0;

            try {
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    'Waktu',
                    'Username',
                    'Nama Pelaku',
                    'Aktivitas',
                    'Detail',
                ]);

                foreach ($query->cursor() as $row) {
                    $cells = [
                        $row->timestamp,
                        $row->username,
                        $row->actor_name,
                        $row->action,
                        $row->detail,
                    ];

                    fputcsv(
                        $handle,
                        array_map([$this, 'safeCsvCell'], $cells),
                        ',',
                        '"',
                        '\\'
                    );

                    $exported++;
                }
            } finally {
                fclose($handle);
            }

            AuditService::log(
                Auth::id(),
                AuditService::REPORT_EXPORTED,
                [
                    'jenis' => 'audits',
                    'format' => 'csv',
                    'filter' => $filters,
                    'jumlah_data' => $exported,
                ]
            );
        }, 'netguard-log-audit.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ekspor PDF log keputusan, maksimal 500 data.
     */
    private function decisionsPdf(array $filters)
    {
        $query = $this->decisionQuery($filters);

        $total = (clone $query)->count('tl.id');

        $logs = $query
            ->limit(self::PDF_LIMIT)
            ->get();

        $pdf = Pdf::loadView('exports.decisions-pdf', [
            'logs' => $logs,
            'total' => $total,
            'limit' => self::PDF_LIMIT,
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();

        AuditService::log(
            Auth::id(),
            AuditService::REPORT_EXPORTED,
            [
                'jenis' => 'decisions',
                'format' => 'pdf',
                'filter' => $filters,
                'jumlah_data' => $logs->count(),
                'total_data' => $total,
                'batas_pdf' => self::PDF_LIMIT,
            ]
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="netguard-log-keputusan.pdf"',
        ]);
    }

    /**
     * Ekspor PDF log audit, maksimal 500 data.
     */
    private function auditsPdf(array $filters)
    {
        $query = $this->auditQuery($filters);

        $total = (clone $query)->count('al.id');

        $logs = $query
            ->limit(self::PDF_LIMIT)
            ->get();

        $pdf = Pdf::loadView('exports.audits-pdf', [
            'logs' => $logs,
            'total' => $total,
            'limit' => self::PDF_LIMIT,
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();

        AuditService::log(
            Auth::id(),
            AuditService::REPORT_EXPORTED,
            [
                'jenis' => 'audits',
                'format' => 'pdf',
                'filter' => $filters,
                'jumlah_data' => $logs->count(),
                'total_data' => $total,
                'batas_pdf' => self::PDF_LIMIT,
            ]
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="netguard-log-audit.pdf"',
        ]);
    }
}
