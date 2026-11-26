<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\ProduksiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur khusus instance produksi (aplikasi Play Store): filter "hanya kos
 * terverifikasi" dan hapus akun mandiri. Juga memastikan instance skripsi
 * (flag mati) tidak berubah perilakunya.
 */
class RilisPlayStoreTest extends TestCase
{
    use RefreshDatabase;

    private function buatKos(): array
    {
        $terverifikasi = Kos::factory()->create(['name' => 'Kos Klien', 'verified_at' => now()]);
        $scraping = Kos::factory()->create(['name' => 'Kos Hasil Scraping']);

        return [$terverifikasi, $scraping];
    }

    public function test_instance_skripsi_tetap_menampilkan_semua_kos(): void
    {
        config(['koskita.hanya_kos_terverifikasi' => false]);
        $this->buatKos();

        $names = collect($this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/kos')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('Kos Klien', $names);
        $this->assertContains('Kos Hasil Scraping', $names);
    }

    public function test_mode_produksi_hanya_menampilkan_kos_terverifikasi(): void
    {
        config(['koskita.hanya_kos_terverifikasi' => true]);
        [, $scraping] = $this->buatKos();
        $user = User::factory()->create();

        $names = collect($this->actingAs($user, 'sanctum')
            ->getJson('/api/kos')->assertOk()->json('data'))->pluck('name');
        $this->assertSame(['Kos Klien'], $names->all());

        $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$scraping->id}")->assertNotFound();
        $this->get("/kos/{$scraping->id}")->assertNotFound();
    }

    public function test_mode_produksi_rekomendasi_tidak_memuat_kos_belum_terverifikasi(): void
    {
        config(['koskita.hanya_kos_terverifikasi' => true]);
        $this->buatKos();
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => 'pria',
            'occupation' => 'mahasiswa',
            'budget_min' => 0,
            'budget_max' => 10000000,
            'preferred_location' => 'Karawaci',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/recommendations')->assertOk();
        $this->assertStringNotContainsString('Kos Hasil Scraping', $response->getContent());
    }

    public function test_pemilik_tetap_melihat_kos_sendiri_yang_belum_diverifikasi(): void
    {
        config(['koskita.hanya_kos_terverifikasi' => true]);
        $owner = User::factory()->create(['role' => 'owner']);
        Kos::factory()->create(['name' => 'Kos Baru Pemilik', 'owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/owner/koses')->assertOk();
        $this->assertStringContainsString('Kos Baru Pemilik', $response->getContent());
    }

    public function test_pengguna_bisa_menghapus_akun_sendiri(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['verified_at' => now()]);
        Booking::create(['user_id' => $user->id, 'kos_id' => $kos->id, 'status' => 'pending', 'start_date' => now()->addDay()->toDateString(), 'duration_months' => 1]);
        $user->createToken('hp');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/profile', ['konfirmasi' => 'HAPUS'])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('bookings', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('koses', ['id' => $kos->id]);
    }

    public function test_hapus_akun_wajib_konfirmasi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile', [])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/profile', ['konfirmasi' => 'hapus'])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_hapus_akun_pemilik_ikut_menghapus_kosnya(): void
    {
        config(['koskita.hanya_kos_terverifikasi' => true]);
        $owner = User::factory()->create(['role' => 'owner']);
        $belum = Kos::factory()->create(['owner_id' => $owner->id]);
        $sudah = Kos::factory()->create(['owner_id' => $owner->id, 'verified_at' => now()]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/profile', ['konfirmasi' => 'HAPUS'])
            ->assertOk();

        $this->assertDatabaseMissing('koses', ['id' => $belum->id]);
        $this->assertDatabaseMissing('koses', ['id' => $sudah->id]);
    }

    public function test_admin_tidak_bisa_hapus_akun_dari_aplikasi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/profile', ['konfirmasi' => 'HAPUS'])
            ->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_halaman_hapus_akun_dan_privasi_bisa_dibuka(): void
    {
        $this->get('/hapus-akun')->assertOk()->assertSee('Hapus Akun KosKita');
        $this->get('/privacy')->assertOk()->assertSee('skripsi')->assertSee('dengan menghubungi kami')->assertDontSee('Profil &gt; Hapus Akun', false);

        config(['koskita.rilis_publik' => true, 'koskita.email_kontak' => 'halo@contoh.id']);
        $this->get('/privacy')->assertOk()->assertDontSee('skripsi')->assertSee('halo@contoh.id')->assertSee('Profil &gt; Hapus Akun', false);
    }

    public function test_produksi_seeder_hanya_mengisi_master_data(): void
    {
        $this->seed(ProduksiSeeder::class);
        $this->seed(ProduksiSeeder::class);

        $this->assertDatabaseCount('facilities', 6);
        $this->assertDatabaseCount('rules', 4);
        $this->assertDatabaseCount('koses', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
