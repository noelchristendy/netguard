<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Log Audit NetGuard</title>

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
            vertical-align: top;
        }

        th {
            background-color: #e5e7eb;
        }

        .detail {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .notice {
            margin: 12px 0;
            color: #92400e;
        }
    </style>
</head>
<body>

    <h2>NETGUARD</h2>
    <div class="subtitle">Laporan Log Audit Pengguna</div>

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
                <th>Username</th>
                <th>Nama Pelaku</th>
                <th>Aktivitas</th>
                <th>Detail</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->timestamp }}</td>
                    <td>{{ $log->username ?? 'Tidak diketahui' }}</td>
                    <td>{{ $log->actor_name ?? 'Tidak diketahui' }}</td>
                    <td>{{ $log->action }}</td>

                    <td class="detail">
                        @php
                            $decoded = json_decode($log->detail ?? '', true);

                            $detail = json_last_error() === JSON_ERROR_NONE
                                && $decoded !== null
                                ? json_encode(
                                    $decoded,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                )
                                : ($log->detail ?: '-');
                        @endphp

                        {{ $detail }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center">
                        Belum ada data log audit.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
