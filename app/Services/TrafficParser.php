<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Membaca dan memvalidasi data trafik dari CSV (dan dari form input satu trafik).
 * Tanpa database: hanya mengubah teks menjadi baris trafik yang sudah dinormalisasi.
 *
 * Baris trafik hasil (siap untuk FilterService dan traffic_logs):
 *   ['timestamp' => 'Y-m-d H:i:s', 'src_ip' => '...', 'dst_ip' => '...', 'protocol' => 'TCP|UDP|ICMP', 'dst_port' => ?int]
 *
 * Baris rusak dilewati dan dilaporkan, tidak membatalkan seluruh berkas.
 * Hanya kegagalan tingkat berkas (header salah, berkas kosong, enkode UTF-16) yang dilaporkan sebagai 'fatal'.
 */
class TrafficParser
{
    public const COLUMNS = ['timestamp', 'src_ip', 'dst_ip', 'protocol', 'dst_port'];

    /** Batas baris data per berkas. Sisanya diabaikan dan 'truncated' bernilai true. */
    public const MAX_ROWS = 50000;

    /** Daftar galat yang dikembalikan dibatasi, jumlah sebenarnya ada di 'error_count'. */
    public const MAX_REPORTED_ERRORS = 100;

    private const PROTOCOLS = ['TCP', 'UDP', 'ICMP'];

    /**
     * @return array{rows: array<int, array>, errors: array<int, array{line:int, message:string}>,
     *               error_count: int, total: int, truncated: bool, fatal: ?string}
     */
    public static function parseFile(string $path, int $maxRows = self::MAX_ROWS): array
    {
        // Diperiksa dulu agar fopen tidak memicu peringatan PHP (tanpa perlu operator @)
        if (! is_file($path) || ! is_readable($path)) {
            return self::fatal('Berkas tidak ditemukan atau tidak dapat dibaca.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return self::fatal('Berkas tidak dapat dibuka.');
        }

        try {
            return self::parseStream($handle, $maxRows);
        } finally {
            fclose($handle);
        }
    }

    public static function parseString(string $csv, int $maxRows = self::MAX_ROWS): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        try {
            return self::parseStream($handle, $maxRows);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Nomor baris pada galat mengikuti urutan baris di berkas: header adalah baris 1.
     *
     * @param resource $handle
     */
    public static function parseStream($handle, int $maxRows = self::MAX_ROWS): array
    {
        $first = fgets($handle);
        if ($first === false || trim($first) === '') {
            return self::fatal('Berkas kosong atau baris pertama (header) tidak ada.');
        }

        if (str_starts_with($first, "\xFF\xFE") || str_starts_with($first, "\xFE\xFF")) {
            return self::fatal('Berkas berenkode UTF-16. Simpan ulang sebagai CSV dengan enkode UTF-8.');
        }
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);   // buang BOM UTF-8 (umum dari Excel)
        }

        $delimiter = self::detectDelimiter($first);
        $header    = array_map(
            fn ($cell) => strtolower(trim((string) $cell)),
            str_getcsv($first, $delimiter, '"', '')
        );

        $map     = [];
        $missing = [];
        foreach (self::COLUMNS as $column) {
            $index = array_search($column, $header, true);
            if ($index === false) {
                $missing[] = $column;
            } else {
                $map[$column] = $index;
            }
        }

        if ($missing !== []) {
            return self::fatal(
                'Header CSV tidak valid. Kolom yang diperlukan: '.implode(', ', self::COLUMNS).
                '. Kolom tidak ditemukan: '.implode(', ', $missing).
                '. Pastikan baris pertama berisi nama kolom dan berkas berenkode UTF-8.'
            );
        }

        $rows       = [];
        $errors     = [];
        $errorCount = 0;
        $total      = 0;
        $truncated  = false;
        $line       = 1;
        $expected   = count($header);

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;

            if (self::allBlank($cells)) {
                continue;   // baris kosong dilewati tanpa dilaporkan
            }

            if ($total >= $maxRows) {
                $truncated = true;
                break;
            }
            $total++;

            if (count($cells) !== $expected) {
                $message = 'jumlah kolom tidak sesuai (ditemukan '.count($cells).', seharusnya '.$expected.')';
            } else {
                $assoc = [];
                foreach (self::COLUMNS as $column) {
                    $assoc[$column] = $cells[$map[$column]];
                }

                $result = self::parseRow($assoc);
                if ($result['data'] !== null) {
                    $rows[] = $result['data'];
                    continue;
                }
                $message = implode('; ', $result['errors']);
            }

            $errorCount++;
            if (count($errors) < self::MAX_REPORTED_ERRORS) {
                $errors[] = ['line' => $line, 'message' => $message];
            }
        }

        return [
            'rows'        => $rows,
            'errors'      => $errors,
            'error_count' => $errorCount,
            'total'       => $total,
            'truncated'   => $truncated,
            'fatal'       => null,
        ];
    }

    /**
     * Memvalidasi dan menormalisasi satu trafik (dari CSV atau form "input satu trafik").
     *
     * Kelonggaran yang disengaja:
     *  - timestamp boleh 'Y-m-d H:i', atau memakai 'T' (input datetime-local); detik diisi 00.
     *  - protokol tidak peka huruf besar kecil.
     *  - port untuk ICMP diabaikan (menjadi null).
     *
     * @param array{timestamp?:mixed, src_ip?:mixed, dst_ip?:mixed, protocol?:mixed, dst_port?:mixed} $input
     * @return array{data: ?array, errors: string[]}
     */
    public static function parseRow(array $input): array
    {
        $errors = [];

        $timestamp = self::normalizeTimestamp(self::text($input['timestamp'] ?? null));
        if ($timestamp === null) {
            $errors[] = 'timestamp tidak valid (format YYYY-MM-DD HH:MM:SS): '.self::show(self::text($input['timestamp'] ?? null));
        }

        $src = self::text($input['src_ip'] ?? null);
        if (! self::validIp($src)) {
            $errors[] = 'IP sumber tidak valid: '.self::show($src);
        }

        $dst = self::text($input['dst_ip'] ?? null);
        if (! self::validIp($dst)) {
            $errors[] = 'IP tujuan tidak valid: '.self::show($dst);
        }

        $protocol = strtoupper(self::text($input['protocol'] ?? null));
        if (! in_array($protocol, self::PROTOCOLS, true)) {
            $errors[] = 'protokol tidak dikenal (harus TCP, UDP, atau ICMP): '.self::show($protocol);
            $protocol = null;
        }

        $portText = self::text($input['dst_port'] ?? null);
        $port     = null;

        if ($protocol !== 'ICMP') {
            if ($portText === '') {
                if ($protocol !== null) {
                    $errors[] = 'port wajib diisi untuk '.$protocol;
                }
            } elseif (! ctype_digit($portText) || (int) $portText < 1 || (int) $portText > 65535) {
                $errors[] = 'port tidak valid (1-65535): '.self::show($portText);
            } else {
                $port = (int) $portText;
            }
        }

        if ($errors !== []) {
            return ['data' => null, 'errors' => $errors];
        }

        return [
            'data' => [
                'timestamp' => $timestamp,
                'src_ip'    => $src,
                'dst_ip'    => $dst,
                'protocol'  => $protocol,
                'dst_port'  => $port,
            ],
            'errors' => [],
        ];
    }

    // ------------------------------------------------------------------ helper

    private static function fatal(string $message): array
    {
        return [
            'rows'        => [],
            'errors'      => [],
            'error_count' => 0,
            'total'       => 0,
            'truncated'   => false,
            'fatal'       => $message,
        ];
    }

    /** Excel versi Indonesia menyimpan CSV dengan titik koma. Pilih pemisah yang paling banyak muncul di header. */
    private static function detectDelimiter(string $headerLine): string
    {
        $best = ',';
        $max  = substr_count($headerLine, ',');

        foreach ([';', "\t"] as $candidate) {
            $count = substr_count($headerLine, $candidate);
            if ($count > $max) {
                $best = $candidate;
                $max  = $count;
            }
        }

        return $best;
    }

    private static function allBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function validIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    private static function normalizeTimestamp(string $value): ?string
    {
        $v = preg_replace('/^(\d{4}-\d{2}-\d{2})T/', '$1 ', trim($value));

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
            $v .= ':00';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $v);

        // format ulang harus sama persis, supaya tanggal mustahil (2026-02-30) tidak "bergulir" ke tanggal lain
        if ($date === false || $date->format('Y-m-d H:i:s') !== $v) {
            return null;
        }

        return $v;
    }

    /** Memotong nilai yang ditampilkan di pesan galat. Teks dari pengguna, selalu di-escape saat ditampilkan. */
    private static function show(string $value): string
    {
        return $value === '' ? '(kosong)' : mb_strimwidth($value, 0, 40, '...');
    }
}
