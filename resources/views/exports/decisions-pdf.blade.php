<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Log Keputusan NetGuard</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #333;
        }

        h2 {
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #e5e7eb;
        }

        .notice {
            margin: 12px 0;
            color: #92400e;
        }
    </style>
</head>
<body>

    <h2>NETGUARD</h2>
    <div class="subtitle">Laporan Log Keputusan Trafik</div>

    <p>
        Tanggal laporan: {{ now()->format('d-m-Y H:i:s') }}
    </p>

    @if ($total > $limit)
        <p class="notice">
            Laporan dibatasi hingga {{ $limit }} data dari total {{ $total }} data.
        </p>
    @else
        <p>Total data: {{ $total }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>IP Sumber</th>
                <th>IP Tujuan</th>
                <th>Protokol</th>
                <th>Port</th>
                <th>Keputusan</th>
                <th>Rule</th>
                <th>Keterangan</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->timestamp }}</td>
                    <td>{{ $log->src_ip }}</td>
                    <td>{{ $log->dst_ip }}</td>
                    <td>{{ $log->protocol }}</td>
                    <td>{{ $log->dst_port ?? '-' }}</td>
                    <td>{{ strtoupper($log->decision) }}</td>

                    <td>
                        @if ($log->rule_id === null)
                            {{ $log->is_default ? 'Default Deny' : 'Rule Terhapus' }}
                        @else
                            Rule #{{ $log->rule_priority }}
                        @endif
                    </td>

                    <td>{{ $log->rule_description ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center">
                        Belum ada data log keputusan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
