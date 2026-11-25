<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    public function test_halaman_awal_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_dashboard_membutuhkan_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_halaman_login_dapat_dibuka(): void
    {
        $this->get('/login')->assertOk()->assertSee('Username');
    }

    public function test_rute_bawaan_breeze_yang_dihapus_tidak_ada(): void
    {
        foreach (['/register', '/forgot-password', '/profile'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }
}