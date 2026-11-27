<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuktikan (bukan cuma mengklaim) bahwa akun dengan email yang sama
 * datanya SELALU sinkron antara situs web dan app Flutter -- karena
 * keduanya membaca/menulis tabel yang sama persis (users, koses, bookings,
 * kos_reviews, user_interactions) di database "koskita" yang sama, tanpa
 * cache/replikasi terpisah apapun di antaranya. Tiap tes di sini menulis
 * lewat satu jalur (web ATAU api) lalu membaca lewat jalur SEBALIKNYA,
 * pada user Eloquent yang sama.
 */
class AccountSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_written_on_website_is_visible_via_api(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        // Ulasan baru cuma boleh dari penyewa yang masa sewanya sudah
        // SELESAI -- lihat WebKosController::storeReview /
        // Booking::userHasCompletedStayAt().
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonth(),
            'duration_months' => 3,
            'status' => 'completed',
        ]);

        // Ditulis lewat situs (guard sesi "web").
        $this->actingAs($user)->post("/kos/{$kos->id}/ulasan", [
            'rating' => 4,
            'comment' => 'Ditulis dari situs web.',
        ])->assertRedirect();

        // Dibaca lewat API (guard token "sanctum", jalur yang dipakai app Flutter).
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");
        $response->assertOk();
        $response->assertJsonFragment(['comment' => 'Ditulis dari situs web.']);
    }

    public function test_booking_created_via_api_is_visible_on_website(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        // Diajukan lewat API (jalur app Flutter).
        $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'kos_id' => $kos->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 6,
        ])->assertStatus(201);

        // Muncul di halaman "Booking Saya" situs web untuk akun yang sama.
        $response = $this->actingAs($user)->get('/booking-saya');
        $response->assertOk();
        $response->assertSee($kos->name);
        $response->assertSee('Menunggu');
    }

    public function test_favorite_toggled_on_website_is_visible_via_api(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $this->actingAs($user)->post("/kos/{$kos->id}/favorit")->assertRedirect();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/favorites');
        $response->assertOk();
        $response->assertJsonFragment(['id' => $kos->id]);
    }

    public function test_profile_preferences_are_the_same_row_for_web_and_api(): void
    {
        // Daftar lewat situs -- profil preferensi default dibuat di sini.
        $this->post('/daftar', [
            'name' => 'Penyewa Sinkron',
            'email' => 'sinkron@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::where('email', 'sinkron@example.com')->firstOrFail();

        // Diubah lewat API (jalur app Flutter mengisi Onboarding).
        $this->actingAs($user, 'sanctum')->postJson('/api/profile', [
            'gender' => 'wanita',
            'occupation' => 'pekerja',
            'budget_min' => 1500000,
            'budget_max' => 2500000,
            'preferred_location' => 'BSD',
        ])->assertOk();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'gender' => 'wanita',
            'preferred_location' => 'BSD',
        ]);
        // Cuma ada SATU baris profil untuk user ini -- bukan salinan
        // terpisah per platform.
        $this->assertDatabaseCount('user_profiles', 1);
    }
}
