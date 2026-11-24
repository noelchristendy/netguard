<?php

declare(strict_types=1);

$outputPath = __DIR__ . DIRECTORY_SEPARATOR . 'traffic_sample.csv';

$rows = [];
$categoryCounts = [];

$addTraffic = static function (string $category, array $data) use (&$rows, &$categoryCounts): void {
    $rows[] = $data;
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
};

for ($i = 1; $i <= 40; $i++) {
    $addTraffic('web_allow', [
        sprintf('2026-11-20 09:%02d:%02d', $i - 1, ($i * 7) % 60),
        '192.168.1.' . (10 + $i),
        '10.0.0.5',
        'TCP',
        '443',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('web_non_lan', [
        sprintf('2026-11-20 10:%02d:%02d', $i, ($i * 3) % 60),
        '172.16.4.' . (10 + $i),
        '10.0.0.5',
        'TCP',
        '443',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('web_http80', [
        sprintf('2026-11-20 11:%02d:%02d', $i, ($i * 5) % 60),
        '192.168.1.' . (50 + $i),
        '10.0.0.5',
        'TCP',
        '80',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('dns', [
        sprintf('2026-11-20 12:%02d:%02d', $i, ($i * 7) % 60),
        '192.168.1.' . (70 + $i),
        '10.0.0.9',
        'UDP',
        '53',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('ssh_jam_kerja', [
        sprintf('2026-11-20 13:%02d:%02d', $i, ($i * 3) % 60),
        '192.168.1.' . (90 + $i),
        '10.0.0.5',
        'TCP',
        '22',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $timestamp = $i === 1
        ? '2026-11-20 22:41:37'
        : sprintf('2026-11-20 22:%02d:%02d', $i, ($i * 5) % 60);

    $srcIp = $i === 1
        ? '172.16.4.8'
        : '172.16.5.' . (10 + $i);

    $addTraffic('ssh_luar_jam', [
        $timestamp,
        $srcIp,
        '10.0.0.5',
        'TCP',
        '22',
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('port_scan', [
        sprintf('2026-11-20 14:%02d:%02d', $i, ($i * 11) % 60),
        '203.0.113.' . (10 + $i),
        '10.0.0.5',
        'TCP',
        (string) $i,
    ]);
}

for ($i = 1; $i <= 20; $i++) {
    $addTraffic('icmp', [
        sprintf('2026-11-20 15:%02d:%02d', $i, ($i * 13) % 60),
        '192.168.1.' . (110 + $i),
        '10.0.0.9',
        'ICMP',
        '',
    ]);
}

for ($i = 1; $i <= 7; $i++) {
    $addTraffic('other_deny', [
        sprintf('2026-11-20 16:%02d:%02d', $i, ($i * 9) % 60),
        '198.51.100.' . (10 + $i),
        '10.0.0.9',
        'UDP',
        (string) (1200 + $i),
    ]);
}

$boundaryTimes = [
    '07:59:59',
    '08:00:00',
    '17:00:00',
    '17:00:01',
];

foreach ($boundaryTimes as $time) {
    $addTraffic('batas_jam', [
        '2026-11-20 ' . $time,
        '172.16.4.8',
        '10.0.0.5',
        'TCP',
        '22',
    ]);
}

if (count($rows) !== 191) {
    throw new RuntimeException(
        'Jumlah trafik valid tidak sesuai: ' . count($rows)
    );
}

$invalidRows = [
    ['2026-11-20 16:00:01', '999.1.1.1', '10.0.0.5', 'TCP', '443'],

    ['2026-11-20 16:00:02', '192.168.1.10', '10.0.0.5', 'HTTP', '80'],

    ['2026-11-20 16:00:03', '192.168.1.10', '10.0.0.5', 'TCP', '70000'],

    ['20-11-2026 16:00:04', '192.168.1.10', '10.0.0.5', 'TCP', '443'],

    ['2026-11-20 16:00:05', '192.168.1.10', '10.0.0.5'],
];

$handle = fopen($outputPath, 'wb');

if ($handle === false) {
    throw new RuntimeException('Gagal membuat file CSV.');
}

$header = [
    'timestamp',
    'src_ip',
    'dst_ip',
    'protocol',
    'dst_port',
];

try {
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    foreach ($invalidRows as $row) {
        fputcsv($handle, $row);
    }
} finally {
    fclose($handle);
}

echo "====================================\n";
echo " NETGUARD TRAFFIC DATA GENERATOR\n";
echo "====================================\n";
echo "File       : {$outputPath}\n";
echo "Data valid : " . count($rows) . "\n";
echo "Data rusak : " . count($invalidRows) . "\n";
echo "Total data : " . (count($rows) + count($invalidRows)) . "\n";

echo "\nRINGKASAN PER KATEGORI\n";

foreach ($categoryCounts as $category => $count) {
    echo "- {$category}: {$count}\n";
}

echo "\nGenerator berhasil dijalankan.\n";
