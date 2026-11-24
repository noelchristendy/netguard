<?php

namespace App\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Mesin evaluasi rule (bagian 4.2 laporan). Tanpa akses database, supaya mudah dites.
 *
 *  - Rule aktif dievaluasi dari priority terkecil.
 *  - allow atau deny pertama yang cocok menentukan keputusan dan menghentikan evaluasi.
 *  - log hanya mencatat rule-nya, lalu evaluasi LANJUT ke rule berikutnya.
 *  - Tidak ada rule penentu: deny (default deny), is_default = true.
 *
 * Bentuk trafik (sudah dinormalisasi oleh TrafficParser):
 *   ['timestamp' => ..., 'src_ip' => ..., 'dst_ip' => ..., 'protocol' => ..., 'dst_port' => ?int]
 *
 * Bentuk hasil:
 *   ['decision' => 'allow'|'deny', 'rule_id' => ?int, 'logged_rule_ids' => int[], 'is_default' => bool]
 */
class FilterService
{
    /** Evaluasi satu trafik. Untuk banyak trafik pakai evaluateMany() agar rule disiapkan sekali saja. */
    public static function evaluate(array $traffic, Collection $rules): array
    {
        return self::decide($traffic, self::prepare($rules));
    }

    /**
     * Evaluasi banyak trafik dengan satu kali persiapan rule (penyaringan dan pengurutan).
     * Hasilnya daftar berindeks 0..n-1 dengan urutan yang sama seperti $traffics.
     *
     * @param iterable<array> $traffics
     */
    public static function evaluateMany(iterable $traffics, Collection $rules): array
    {
        $prepared = self::prepare($rules);
        $results  = [];

        foreach ($traffics as $traffic) {
            $results[] = self::decide($traffic, $prepared);
        }

        return $results;
    }

    /**
     * Mengubah logged_rule_ids ke nilai untuk kolom JSON di traffic_logs.
     * Daftar kosong disimpan sebagai NULL (dasbor menghitung aksi log dari kolom yang tidak null).
     * Wajib dipakai saat insert massal, karena insert() melewati cast Eloquent.
     */
    public static function encodeLoggedIds(array $ids): ?string
    {
        return $ids === [] ? null : json_encode(array_values($ids));
    }

    // ------------------------------------------------------------------ internal

    /** Buang rule nonaktif, lalu urutkan menurut priority. Atribut enabled yang tidak ada dianggap aktif. */
    private static function prepare(Collection $rules): array
    {
        return $rules
            ->filter(fn ($rule) => (bool) (self::attr($rule, 'enabled') ?? true))
            ->sortBy(fn ($rule) => (int) self::attr($rule, 'priority'))
            ->values()
            ->all();
    }

    private static function decide(array $traffic, array $rules): array
    {
        $logged = [];

        foreach ($rules as $rule) {
            if (! RuleMatcher::matches($traffic, $rule)) {
                continue;
            }

            $action = strtolower((string) self::attr($rule, 'action'));
            $id     = self::attr($rule, 'id');

            if ($action === 'log') {
                $logged[] = $id;
                continue;
            }

            if ($action === 'allow' || $action === 'deny') {
                return [
                    'decision'        => $action,
                    'rule_id'         => $id,
                    'logged_rule_ids' => $logged,
                    'is_default'      => false,
                ];
            }

            throw new InvalidArgumentException("Aksi rule tidak dikenal: '{$action}' (rule {$id}).");
        }

        return [
            'decision'        => 'deny',
            'rule_id'         => null,
            'logged_rule_ids' => $logged,
            'is_default'      => true,
        ];
    }

    private static function attr(array|object $rule, string $key): mixed
    {
        return is_array($rule) ? ($rule[$key] ?? null) : ($rule->{$key} ?? null);
    }
}
