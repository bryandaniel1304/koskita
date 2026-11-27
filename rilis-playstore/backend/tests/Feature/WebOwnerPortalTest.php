<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebOwnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_tenant_cannot_access_owner_portal(): void
    {
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant)->get('/pemilik');

        $response->assertRedirect(route('web.home'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/pemilik');

        $response->assertRedirect(route('web.login'));
    }

    public function test_owner_sees_dashboard_with_own_stats(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->get('/pemilik');

        $response->assertOk();
        $response->assertSee($kos->name);
        $response->assertSee('Booking Menunggu');
    }

    public function test_owner_only_sees_own_koses_in_list(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $myKos = Kos::factory()->create(['owner_id' => $owner->id, 'name' => 'Kos Milikku']);
        Kos::factory()->create(['owner_id' => $otherOwner->id, 'name' => 'Kos Orang Lain']);

        $response = $this->actingAs($owner)->get('/pemilik/kos');

        $response->assertOk();
        $response->assertSee($myKos->name);
        $response->assertDontSee('Kos Orang Lain');
    }

    public function test_owner_can_confirm_a_pending_booking(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'total_rooms' => 5]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post("/pemilik/booking/{$booking->id}/status", ['status' => 'confirmed']);

        $response->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_owner_cannot_change_status_of_other_owners_booking(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post("/pemilik/booking/{$booking->id}/status", ['status' => 'confirmed']);

        $response->assertStatus(404);
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_owner_can_toggle_payment_status(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($owner)->post("/pemilik/booking/{$booking->id}/pembayaran", ['payment_status' => 'paid']);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_owner_can_reply_to_review_on_own_kos(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $review = KosReview::create([
            'kos_id' => $kos->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
            'comment' => 'Lumayan bagus.',
        ]);

        $response = $this->actingAs($owner)->post("/pemilik/kos/{$kos->id}/ulasan/{$review->id}/balas", [
            'reply' => 'Terima kasih atas ulasannya!',
        ]);

        $response->assertRedirect();
        $review->refresh();
        $this->assertSame('Terima kasih atas ulasannya!', $review->owner_reply);
        $this->assertNotNull($review->owner_replied_at);
    }
}
