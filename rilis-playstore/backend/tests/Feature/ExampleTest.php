<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Root sekarang menampilkan beranda situs publik KosKita (bukan lagi
     * redirect ke login admin) -- lihat routes/web.php & WebHomeController.
     * Panel admin tetap ada, cuma dipindah ke /login (diakses langsung,
     * tidak lagi jadi tujuan redirect dari "/").
     */
    public function test_root_shows_public_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('KosKita');
    }

    public function test_admin_login_page_still_reachable_directly(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }
}
