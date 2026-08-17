<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function bookingWithStatus(User $user, Kos $kos, string $status): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonth(),
            'duration_months' => 3,
            'status' => $status,
        ]);
    }

    public function test_api_can_review_is_false_with_no_booking_at_all(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk()->assertJsonPath('kos.can_review', false);
    }

    public function test_api_can_review_is_false_with_only_a_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $this->bookingWithStatus($user, $kos, 'confirmed');

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk()->assertJsonPath('kos.can_review', false);
    }

    public function test_api_can_review_is_true_with_a_completed_booking(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $this->bookingWithStatus($user, $kos, 'completed');

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk()->assertJsonPath('kos.can_review', true);
    }

    public function test_api_can_review_is_true_when_a_review_already_exists_even_without_a_booking(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        KosReview::create(['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 5]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk()->assertJsonPath('kos.can_review', true);
    }

    public function test_web_kos_page_hides_the_review_form_without_a_completed_booking(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $kos = Kos::factory()->create();
        $this->bookingWithStatus($user, $kos, 'confirmed');

        $response = $this->actingAs($user)->get("/kos/{$kos->id}");

        $response->assertOk()
            ->assertDontSee('Tulis ulasan kamu')
            ->assertSee('Ulasan cuma bisa ditulis setelah masa sewamu di kos ini selesai.');
    }

    public function test_web_kos_page_shows_the_review_form_with_a_completed_booking(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $kos = Kos::factory()->create();
        $this->bookingWithStatus($user, $kos, 'completed');

        $response = $this->actingAs($user)->get("/kos/{$kos->id}");

        $response->assertOk()->assertSee('Tulis ulasan kamu');
    }

    public function test_review_prompt_notification_appears_after_a_completed_stay(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['name' => 'Kos Kenangan']);
        $this->bookingWithStatus($user, $kos, 'completed');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'review_prompt']);
    }

    public function test_review_prompt_notification_disappears_once_reviewed(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $this->bookingWithStatus($user, $kos, 'completed');
        KosReview::create(['user_id' => $user->id, 'kos_id' => $kos->id, 'rating' => 5]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $types = collect($response->json('notifications'))->pluck('type');
        $this->assertFalse($types->contains('review_prompt'));
    }
}
