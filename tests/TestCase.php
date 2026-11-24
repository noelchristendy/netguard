<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh bergantung pada hasil build aset (npm run build / npm run dev).
        // Tanpa ini, halaman yang memakai @vite (termasuk halaman 403) menghasilkan error 500.
        $this->withoutVite();
    }

    /**
     * Pengaman: RefreshDatabase mengosongkan database. Hentikan tes bila koneksi yang dipakai
     * bukan database tes, supaya data utama (netguard) tidak pernah terhapus.
     *
     * Dipanggil sebelum trait seperti RefreshDatabase dijalankan.
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $database   = (string) config("database.connections.{$connection}.database");

        $aman = $connection === 'sqlite' || str_ends_with($database, '_test');

        if (! $aman) {
            throw new RuntimeException(
                "Tes dihentikan: koneksi '{$connection}' dengan database '{$database}' bukan database tes. ".
                "Periksa phpunit.xml: DB_CONNECTION=mysql dan DB_DATABASE=netguard_test (atau sqlite dengan :memory:). ".
                "Jalankan php artisan config:clear bila konfigurasi pernah di-cache."
            );
        }

        return parent::setUpTraits();
    }
}