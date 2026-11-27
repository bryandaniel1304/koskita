<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use App\Models\Waitlist;
use App\Notifications\WaitlistSpotAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WaitlistNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBooking(Kos $kos, string $status): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => $status,
        ]);
    }

    public function test_cancelling_a_confirmed_booking_notifies_waitlisted_users(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $booking = $this->makeBooking($kos, 'confirmed');
        $waiter = User::factory()->create();
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id]);

        $booking->update(['status' => 'cancelled']);

        Notification::assertSentTo($waiter, WaitlistSpotAvailable::class);
        $this->assertNotNull(Waitlist::first()->notified_at);
    }

    public function test_completing_a_booking_also_frees_the_room_and_notifies(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $booking = $this->makeBooking($kos, 'confirmed');
        $waiter = User::factory()->create();
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id]);

        $booking->update(['status' => 'completed']);

        Notification::assertSentTo($waiter, WaitlistSpotAvailable::class);
    }

    public function test_a_booking_that_was_never_confirmed_does_not_trigger_a_notification(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 5]);
        $booking = $this->makeBooking($kos, 'pending');
        $waiter = User::factory()->create();
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id]);

        $booking->update(['status' => 'rejected']);

        Notification::assertNotSentTo($waiter, WaitlistSpotAvailable::class);
    }

    public function test_increasing_total_rooms_notifies_waitlisted_users(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $this->makeBooking($kos, 'confirmed'); // penuh
        $waiter = User::factory()->create();
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id]);

        $kos->update(['total_rooms' => 2]);

        Notification::assertSentTo($waiter, WaitlistSpotAvailable::class);
    }

    public function test_a_waitlist_entry_that_was_already_notified_is_not_notified_twice(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $waiter = User::factory()->create();
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id, 'notified_at' => now()]);
        $booking = $this->makeBooking($kos, 'confirmed');

        $booking->update(['status' => 'cancelled']);

        Notification::assertNotSentTo($waiter, WaitlistSpotAvailable::class);
    }

    public function test_freeing_a_room_on_one_kos_does_not_notify_waiters_on_another_kos(): void
    {
        Notification::fake();
        $kosA = Kos::factory()->create(['total_rooms' => 1]);
        $kosB = Kos::factory()->create(['total_rooms' => 1]);
        $bookingA = $this->makeBooking($kosA, 'confirmed');
        $waiterOnB = User::factory()->create();
        Waitlist::create(['user_id' => $waiterOnB->id, 'kos_id' => $kosB->id]);

        $bookingA->update(['status' => 'cancelled']);

        Notification::assertNotSentTo($waiterOnB, WaitlistSpotAvailable::class);
    }
}
