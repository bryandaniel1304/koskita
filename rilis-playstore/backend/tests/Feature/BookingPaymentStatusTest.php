<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function verifiedOwner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_new_booking_defaults_to_unpaid(): void
    {
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => Kos::factory()->create()->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
        $this->assertNull($booking->fresh()->paid_at);
    }

    public function test_owner_can_mark_booking_as_paid(): void
    {
        $owner = $this->verifiedOwner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/bookings/{$booking->id}/payment-status", [
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_owner_cannot_mark_payment_status_on_other_owners_booking(): void
    {
        $owner = $this->verifiedOwner();
        $otherOwner = $this->verifiedOwner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/bookings/{$booking->id}/payment-status", [
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(404);
        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }
}
