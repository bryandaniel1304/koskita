<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur situs publik KosKita (guard sesi "web", TERPISAH dari panel admin
 * dan API Sanctum) -- register/login, katalog & detail kos, dan gerbang
 * verifikasi email untuk booking/ulasan, sama seperti yang dites di sisi
 * API tapi lewat rute & middleware situs (auth.web / verified.web).
 */
class WebSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_home_and_catalog(): void
    {
        Kos::factory()->count(3)->create();

        $this->get('/')->assertOk()->assertSee('KosKita');
        $this->get('/kos')->assertOk();
    }

    public function test_guest_can_view_kos_detail(): void
    {
        $kos = Kos::factory()->create(['name' => 'Kos Uji Situs']);

        $this->get('/kos/' . $kos->id)->assertOk()->assertSee('Kos Uji Situs');
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/daftar', [
            'name' => 'Penyewa Uji',
            'email' => 'penyewa.uji@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('web.home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'penyewa.uji@example.com', 'role' => 'user']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => User::where('email', 'penyewa.uji@example.com')->first()->id]);
    }

    public function test_guest_is_redirected_to_web_login_not_admin_login(): void
    {
        $response = $this->get('/booking-saya');

        $response->assertRedirect(route('web.login'));
    }

    public function test_unverified_user_cannot_submit_booking_on_website(): void
    {
        $user = User::factory()->unverified()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/booking", [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 3,
        ]);

        $response->assertSessionHasErrors('verification');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_verified_user_can_submit_booking_on_website(): void
    {
        $user = User::factory()->create(); // factory default: email_verified_at = now()
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/booking", [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 3,
        ]);

        $response->assertRedirect(route('web.bookings.index'));
        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'kos_id' => $kos->id, 'status' => 'pending']);
    }

    public function test_website_booking_blocked_when_kos_is_full(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        \App\Models\Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDay(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/booking", [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 3,
        ]);

        $response->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_verified_user_can_submit_review_on_website(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        // Ulasan baru sekarang cuma boleh dari penyewa yang masa sewanya
        // sudah SELESAI (parity dengan Api\ReviewController -- lihat
        // WebKosController::storeReview / Booking::userHasCompletedStayAt).
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonth(),
            'duration_months' => 3,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/ulasan", [
            'rating' => 5,
            'comment' => 'Nyaman dan bersih.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kos_reviews', ['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 5]);
    }

    public function test_review_without_confirmed_booking_is_rejected_on_website(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/ulasan", [
            'rating' => 5,
            'comment' => 'Coba ulasan tanpa booking.',
        ]);

        $response->assertSessionHasErrors('review');
        $this->assertDatabaseCount('kos_reviews', 0);
    }
}
