<?php

namespace Tests\Unit;

use App\Services\FilterService;
use App\Services\TrafficParser;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TrafficParserTest extends TestCase
{
    private const HEADER = 'timestamp,src_ip,dst_ip,protocol,dst_port';

    private function csv(string ...$lines): string
    {
        return implode("\n", $lines)."\n";
    }

    // ------------------------------------------------------------------ CSV valid

    public function test_csv_valid_dari_contoh_laporan(): void
    {
        $r = TrafficParser::parseString($this->csv(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443',
            '2026-11-20 22:41:37,172.16.4.8,10.0.0.5,TCP,22',
            '2026-11-20 10:02:11,192.168.1.25,10.0.0.9,ICMP,'
        ));

        $this->assertNull($r['fatal']);
        $this->assertSame(3, $r['total']);
        $this->assertSame(0, $r['error_count']);
        $this->assertCount(3, $r['rows']);

        $this->assertSame([
            'timestamp' => '2026-11-20 09:15:02', 'src_ip' => '192.168.1.10', 'dst_ip' => '10.0.0.5',
            'protocol' => 'TCP', 'dst_port' => 443,
        ], $r['rows'][0]);

        // ICMP tanpa port: dst_port null (bukan string kosong)
        $this->assertNull($r['rows'][2]['dst_port']);
        $this->assertSame('ICMP', $r['rows'][2]['protocol']);
    }

    // ------------------------------------------------------------------ baris rusak

    public function test_baris_rusak_dilewati_dan_dilaporkan_dengan_nomor_baris(): void
    {
        $r = TrafficParser::parseString($this->csv(
            self::HEADER,                                          // baris 1
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443',   // 2 valid
            '2026-11-20 11:00:00,999.1.1.1,10.0.0.5,TCP,443',      // 3 IP tidak valid
            '2026-11-20 11:01:00,192.168.1.10,10.0.0.5,HTTP,443',  // 4 protokol tidak dikenal
            '2026-11-20 11:02:00,192.168.1.10,10.0.0.5,TCP,70000', // 5 port di luar rentang
            'kemarin sore,192.168.1.10,10.0.0.5,TCP,443',          // 6 timestamp salah
            '2026-11-20 11:05:00,192.168.1.10,10.0.0.5',           // 7 kolom kurang
            '2026-11-20 10:02:11,192.168.1.25,10.0.0.9,ICMP,'      // 8 valid
        ));

        $this->assertCount(2, $r['rows']);               // dua baris valid tetap diproses
        $this->assertSame(7, $r['total']);
        $this->assertSame(5, $r['error_count']);
        $this->assertSame([3, 4, 5, 6, 7], array_column($r['errors'], 'line'));

        $messages = array_column($r['errors'], 'message', 'line');
        $this->assertStringContainsString('IP sumber tidak valid', $messages[3]);
        $this->assertStringContainsString('999.1.1.1', $messages[3]);
        $this->assertStringContainsString('protokol tidak dikenal', $messages[4]);
        $this->assertStringContainsString('port tidak valid', $messages[5]);
        $this->assertStringContainsString('timestamp tidak valid', $messages[6]);
        $this->assertStringContainsString('jumlah kolom tidak sesuai', $messages[7]);
    }

    public function test_satu_baris_dengan_beberapa_galat_melaporkan_semuanya(): void
    {
        $r = TrafficParser::parseString($this->csv(self::HEADER, 'xx,999.1.1.1,abc,HTTP,0'));

        $this->assertCount(0, $r['rows']);
        $message = $r['errors'][0]['message'];
        foreach (['timestamp tidak valid', 'IP sumber tidak valid', 'IP tujuan tidak valid', 'protokol tidak dikenal', 'port tidak valid'] as $part) {
            $this->assertStringContainsString($part, $message);
        }
    }

    // ------------------------------------------------------------------ ketahanan format berkas

    public function test_bom_utf8_crlf_dan_baris_kosong_ditangani(): void
    {
        $csv = "\xEF\xBB\xBF".self::HEADER."\r\n"
            ."2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443\r\n"
            ."\r\n"                                                // baris 3 kosong: dilewati
            ."2026-11-20 11:00:00,999.1.1.1,10.0.0.5,TCP,443\r\n";  // baris 4 rusak

        $r = TrafficParser::parseString($csv);

        $this->assertNull($r['fatal']);
        $this->assertCount(1, $r['rows']);
        $this->assertSame(2, $r['total']);                          // baris kosong tidak dihitung
        $this->assertSame(4, $r['errors'][0]['line']);              // nomor baris tetap sesuai berkas
    }

    public function test_pemisah_titik_koma_dari_excel_indonesia(): void
    {
        $r = TrafficParser::parseString($this->csv(
            'timestamp;src_ip;dst_ip;protocol;dst_port',
            '2026-11-20 09:15:02;192.168.1.10;10.0.0.5;TCP;443'
        ));

        $this->assertNull($r['fatal']);
        $this->assertCount(1, $r['rows']);
        $this->assertSame(443, $r['rows'][0]['dst_port']);
    }

    public function test_urutan_kolom_bebas_dan_nama_kolom_tidak_peka_huruf_besar_kecil(): void
    {
        $r = TrafficParser::parseString($this->csv(
            'Timestamp, DST_PORT ,protocol,src_ip,dst_ip',
            '2026-11-20 09:15:02,443,tcp,192.168.1.10,10.0.0.5'
        ));

        $this->assertNull($r['fatal']);
        $this->assertSame([
            'timestamp' => '2026-11-20 09:15:02', 'src_ip' => '192.168.1.10', 'dst_ip' => '10.0.0.5',
            'protocol' => 'TCP', 'dst_port' => 443,
        ], $r['rows'][0]);
    }

    public function test_kegagalan_tingkat_berkas_dilaporkan_sebagai_fatal(): void
    {
        $kosong = TrafficParser::parseString('');
        $this->assertNotNull($kosong['fatal']);
        $this->assertSame([], $kosong['rows']);

        $tanpaPort = TrafficParser::parseString($this->csv('timestamp,src_ip,dst_ip,protocol', '2026-11-20 09:15:02,1.1.1.1,2.2.2.2,TCP'));
        $this->assertStringContainsString('dst_port', $tanpaPort['fatal']);

        $utf16 = TrafficParser::parseString("\xFF\xFEt\0i\0m\0e\0");
        $this->assertStringContainsString('UTF-16', $utf16['fatal']);

        $headerSalah = TrafficParser::parseString($this->csv('2026-11-20 09:15:02,1.1.1.1,2.2.2.2,TCP,80'));
        $this->assertNotNull($headerSalah['fatal']);
    }

    public function test_batas_jumlah_baris(): void
    {
        $lines = [self::HEADER];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443';
        }

        $r = TrafficParser::parseString($this->csv(...$lines), 2);

        $this->assertCount(2, $r['rows']);
        $this->assertSame(2, $r['total']);
        $this->assertTrue($r['truncated']);

        $semua = TrafficParser::parseString($this->csv(...$lines), 10);
        $this->assertFalse($semua['truncated']);
        $this->assertCount(5, $semua['rows']);
    }

    public function test_daftar_galat_dibatasi_tetapi_jumlah_sebenarnya_dihitung(): void
    {
        $lines = [self::HEADER];
        for ($i = 0; $i < 150; $i++) {
            $lines[] = '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,HTTP,443';
        }

        $r = TrafficParser::parseString($this->csv(...$lines));

        $this->assertSame(150, $r['error_count']);
        $this->assertCount(TrafficParser::MAX_REPORTED_ERRORS, $r['errors']);
        $this->assertCount(0, $r['rows']);
    }

    public function test_parse_file_membaca_berkas_di_disk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'trf');
        file_put_contents($path, $this->csv(self::HEADER, '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,UDP,53'));

        try {
            $r = TrafficParser::parseFile($path);
            $this->assertCount(1, $r['rows']);
            $this->assertSame('UDP', $r['rows'][0]['protocol']);
        } finally {
            unlink($path);
        }

        $this->assertNotNull(TrafficParser::parseFile($path.'-tidak-ada')['fatal']);
    }

    // ------------------------------------------------------------------ parseRow (juga dipakai form input satu trafik)

    public function test_timestamp_beberapa_format_dinormalisasi(): void
    {
        $cases = [
            '2026-11-20 09:15:02'  => '2026-11-20 09:15:02',
            '2026-11-20 09:15'     => '2026-11-20 09:15:00',
            '2026-11-20T09:15'     => '2026-11-20 09:15:00',   // input datetime-local
            '2026-11-20T09:15:30'  => '2026-11-20 09:15:30',
            '  2026-11-20 09:15:02 ' => '2026-11-20 09:15:02',
        ];

        foreach ($cases as $input => $expected) {
            $r = TrafficParser::parseRow(['timestamp' => $input, 'src_ip' => '1.1.1.1', 'dst_ip' => '2.2.2.2', 'protocol' => 'TCP', 'dst_port' => '80']);
            $this->assertSame($expected, $r['data']['timestamp'] ?? null, "timestamp '$input'");
        }
    }

    public function test_timestamp_tidak_valid_ditolak(): void
    {
        foreach (['2026-02-30 10:00:00', '2026-11-20 25:00:00', '2026-11-20 10:00:60', '20/11/2026 10:00', '', 'kemarin'] as $bad) {
            $r = TrafficParser::parseRow(['timestamp' => $bad, 'src_ip' => '1.1.1.1', 'dst_ip' => '2.2.2.2', 'protocol' => 'TCP', 'dst_port' => '80']);
            $this->assertNull($r['data'], "timestamp '$bad' seharusnya ditolak");
        }
    }

    public function test_aturan_port_menurut_protokol(): void
    {
        $base = ['timestamp' => '2026-11-20 10:00:00', 'src_ip' => '1.1.1.1', 'dst_ip' => '2.2.2.2'];

        // TCP dan UDP wajib punya port 1-65535
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'TCP', 'dst_port' => ''])['data']);
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'UDP', 'dst_port' => '0'])['data']);
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'UDP', 'dst_port' => '65536'])['data']);
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'TCP', 'dst_port' => 'abc'])['data']);
        $this->assertSame(53, TrafficParser::parseRow($base + ['protocol' => 'udp', 'dst_port' => '53'])['data']['dst_port']);
        $this->assertSame(65535, TrafficParser::parseRow($base + ['protocol' => 'TCP', 'dst_port' => '65535'])['data']['dst_port']);

        // ICMP tidak punya port: kosong diterima, port yang diisi diabaikan menjadi null
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'ICMP', 'dst_port' => ''])['data']['dst_port']);
        $this->assertNull(TrafficParser::parseRow($base + ['protocol' => 'icmp', 'dst_port' => '22'])['data']['dst_port']);
    }

    public function test_spasi_dibuang_dan_ip_ketat(): void
    {
        $ok = TrafficParser::parseRow(['timestamp' => '2026-11-20 10:00:00', 'src_ip' => ' 192.168.1.10 ', 'dst_ip' => '10.0.0.5', 'protocol' => ' tcp ', 'dst_port' => ' 443 ']);
        $this->assertSame('192.168.1.10', $ok['data']['src_ip']);
        $this->assertSame('TCP', $ok['data']['protocol']);
        $this->assertSame(443, $ok['data']['dst_port']);

        foreach (['999.1.1.1', '192.168.1', '192.168.01.1', '::1', 'abc', '', '10.0.0.5/24'] as $bad) {
            $r = TrafficParser::parseRow(['timestamp' => '2026-11-20 10:00:00', 'src_ip' => $bad, 'dst_ip' => '10.0.0.5', 'protocol' => 'TCP', 'dst_port' => '80']);
            $this->assertNull($r['data'], "IP '$bad' seharusnya ditolak");
        }
    }

    // ------------------------------------------------------------------ kontrak dengan FilterService (D2)

    public function test_hasil_parser_langsung_bisa_dievaluasi_filter_service(): void
    {
        $r = TrafficParser::parseString($this->csv(
            self::HEADER,
            '2026-11-20 09:15:02,192.168.1.10,10.0.0.5,TCP,443',
            '2026-11-20 10:02:11,192.168.1.25,10.0.0.9,ICMP,'
        ));

        $rules = Collection::make([
            ['id' => 1, 'priority' => 10, 'src_ip' => '192.168.1.0/24', 'dst_ip' => null, 'protocol' => 'TCP', 'dst_port' => 443, 'time_start' => null, 'time_end' => null, 'action' => 'allow', 'enabled' => true],
            ['id' => 2, 'priority' => 30, 'src_ip' => null, 'dst_ip' => null, 'protocol' => 'ICMP', 'dst_port' => null, 'time_start' => null, 'time_end' => null, 'action' => 'log', 'enabled' => true],
        ]);

        $results = FilterService::evaluateMany($r['rows'], $rules);

        $this->assertSame('allow', $results[0]['decision']);
        $this->assertSame(1, $results[0]['rule_id']);
        $this->assertSame([2], $results[1]['logged_rule_ids']);   // ICMP tanpa port dicatat oleh rule log
        $this->assertTrue($results[1]['is_default']);             // tidak ada rule penentu
    }
}
