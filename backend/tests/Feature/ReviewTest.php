<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function completedBooking(User $user, Kos $kos): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonth(),
            'duration_months' => 3,
            'status' => 'completed',
        ]);
    }

    public function test_verified_user_can_submit_review(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $this->completedBooking($user, $kos);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", [
            'rating' => 5,
            'comment' => 'Kosnya bersih dan nyaman!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('kos_reviews', ['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 5]);
    }

    public function test_user_without_any_booking_cannot_submit_review(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('kos_reviews', 0);
    }

    /**
     * Ini yang berubah dari perilaku lama -- dulu status "confirmed" saja
     * (baru disetujui pemilik, belum tentu sudah menginap/selesai) sudah
     * cukup buat menulis ulasan. Sekarang wajib "completed".
     */
    public function test_user_with_only_a_confirmed_not_yet_completed_booking_cannot_submit_review(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('kos_reviews', 0);
    }

    public function test_unverified_user_cannot_submit_review(): void
    {
        $user = User::factory()->unverified()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", [
            'rating' => 4,
        ]);

        $response->assertStatus(403);
    }

    public function test_resubmitting_review_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $this->completedBooking($user, $kos);

        $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", ['rating' => 3, 'comment' => 'Lumayan']);
        $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/reviews", ['rating' => 5, 'comment' => 'Setelah dipikir lagi, bagus!']);

        $this->assertDatabaseCount('kos_reviews', 1);
        $this->assertDatabaseHas('kos_reviews', ['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 5]);
    }

    public function test_kos_average_review_rating_reflects_submitted_reviews(): void
    {
        $kos = Kos::factory()->create();
        $reviewer1 = User::factory()->create();
        $reviewer2 = User::factory()->create();

        KosReview::create(['user_id' => $reviewer1->id, 'kos_id' => $kos->id, 'rating' => 4]);
        KosReview::create(['user_id' => $reviewer2->id, 'kos_id' => $kos->id, 'rating' => 2]);

        $fresh = Kos::with('reviews')->findOrFail($kos->id);

        $this->assertEquals(3.0, $fresh->average_review_rating);
        $this->assertEquals(2, $fresh->reviews_count);
    }
}
