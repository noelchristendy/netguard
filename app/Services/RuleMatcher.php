<?php

namespace App\Services;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Pencocokan satu trafik terhadap satu rule. Fungsi murni: tanpa database, tanpa state.
 *
 * Semantik (bagian 4 laporan):
 *  - Atribut rule yang kosong (null atau string kosong) cocok untuk semua nilai.
 *  - Rule cocok bila SEMUA atribut yang terisi terpenuhi.
 *  - Hanya IPv4. IP tunggal diperlakukan sebagai /32.
 *  - Jendela waktu: start inklusif, end eksklusif, boleh melewati tengah malam.
 *    Contoh 17:00-08:00: 17:00:00 masuk jendela, 08:00:00 tidak.
 */
class RuleMatcher
{
    /**
     * @param array{timestamp?:mixed, src_ip?:?string, dst_ip?:?string, protocol?:?string, dst_port?:mixed} $traffic
     * @param array|object $rule  Model Rule, stdClass, atau array dengan kolom src_ip, dst_ip, protocol,
     *                            dst_port, time_start, time_end
     *
     * @throws InvalidArgumentException bila jendela waktu rule tidak valid (harus dicegah oleh StoreRuleRequest)
     */
    public static function matches(array $traffic, array|object $rule): bool
    {
        return self::matchIp($traffic['src_ip'] ?? null, self::attr($rule, 'src_ip'))
            && self::matchIp($traffic['dst_ip'] ?? null, self::attr($rule, 'dst_ip'))
            && self::matchProtocol($traffic['protocol'] ?? null, self::attr($rule, 'protocol'))
            && self::matchPort($traffic['dst_port'] ?? null, self::attr($rule, 'dst_port'))
            && self::matchTime(
                $traffic['timestamp'] ?? null,
                self::attr($rule, 'time_start'),
                self::attr($rule, 'time_end')
            );
    }

    /** IP trafik terhadap IP atau CIDR milik rule (null berarti cocok semua). */
    public static function matchIp(?string $ip, ?string $cidr): bool
    {
        $cidr = self::blankToNull($cidr);
        if ($cidr === null) {
            return true;
        }

        $ipLong = $ip === null ? null : self::ipToInt(trim($ip));
        if ($ipLong === null) {
            return false;
        }

        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, '32');

        $netLong = self::ipToInt(trim($network));
        $prefix  = trim($prefix);
        if ($netLong === null || ! ctype_digit($prefix) || (int) $prefix > 32) {
            return false;
        }

        $prefix = (int) $prefix;
        $mask   = $prefix === 0 ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);

        return ($ipLong & $mask) === ($netLong & $mask);
    }

    /** Protokol trafik terhadap protokol rule (TCP, UDP, ICMP, any). Tidak peka huruf besar kecil. */
    public static function matchProtocol(?string $trafficProtocol, ?string $ruleProtocol): bool
    {
        $rule = self::blankToNull($ruleProtocol);
        if ($rule === null || strcasecmp($rule, 'any') === 0) {
            return true;
        }

        $traffic = self::blankToNull($trafficProtocol);

        return $traffic !== null && strcasecmp($traffic, $rule) === 0;
    }

    /**
     * Port rule null berarti semua port. Bila rule menyebut port tetapi trafik tidak punya port
     * (mis. ICMP), hasilnya TIDAK cocok.
     */
    public static function matchPort(int|string|null $trafficPort, int|string|null $rulePort): bool
    {
        $rule = self::blankToNull($rulePort);
        if ($rule === null) {
            return true;
        }

        $traffic = self::blankToNull($trafficPort);
        if ($traffic === null || ! ctype_digit($rule) || ! ctype_digit($traffic)) {
            return false;
        }

        return (int) $rule === (int) $traffic;
    }

    /**
     * Jam trafik terhadap jendela waktu rule. Hanya jam dari timestamp TRAFIK yang diperiksa,
     * bukan waktu sekarang.
     *
     * $time boleh DateTimeInterface (termasuk Carbon) atau string yang diakhiri jam
     * (HH:MM, HH:MM:SS, atau 'Y-m-d H:i:s'). Timestamp dengan zona waktu di ujungnya
     * sebaiknya diubah ke Carbon dulu.
     *
     * @throws InvalidArgumentException bila hanya salah satu batas terisi, formatnya salah, atau start == end
     */
    public static function matchTime(DateTimeInterface|string|null $time, ?string $start, ?string $end): bool
    {
        $start = self::blankToNull($start);
        $end   = self::blankToNull($end);

        if ($start === null && $end === null) {
            return true;
        }
        if ($start === null || $end === null) {
            throw new InvalidArgumentException('time_start dan time_end harus diisi keduanya atau dikosongkan keduanya.');
        }

        $s = self::secondsOfDay($start);
        $e = self::secondsOfDay($end);
        if ($s === null || $e === null) {
            throw new InvalidArgumentException('Format jam rule tidak valid (gunakan HH:MM atau HH:MM:SS).');
        }
        if ($s === $e) {
            throw new InvalidArgumentException('time_start dan time_end tidak boleh sama.');
        }

        $t = $time === null ? null : self::secondsOfDay($time);
        if ($t === null) {
            return false;
        }

        return $s < $e
            ? ($t >= $s && $t < $e)        // jendela biasa, mis. 08:00-17:00
            : ($t >= $s || $t < $e);       // melewati tengah malam, mis. 17:00-08:00
    }

    // ------------------------------------------------------------------ helper

    private static function attr(array|object $rule, string $key): mixed
    {
        return is_array($rule) ? ($rule[$key] ?? null) : ($rule->{$key} ?? null);
    }

    private static function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function ipToInt(string $ip): ?int
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        $long = ip2long($ip);

        return $long === false ? null : $long;
    }

    /** Detik sejak 00:00:00, atau null bila formatnya tidak valid. */
    private static function secondsOfDay(DateTimeInterface|string $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return ((int) $value->format('G')) * 3600
                + ((int) $value->format('i')) * 60
                + (int) $value->format('s');
        }

        if (! preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return null;
        }

        $h   = (int) $m[1];
        $min = (int) $m[2];
        $sec = isset($m[3]) ? (int) $m[3] : 0;

        if ($h > 23 || $min > 59 || $sec > 59) {
            return null;
        }

        return $h * 3600 + $min * 60 + $sec;
    }
}
