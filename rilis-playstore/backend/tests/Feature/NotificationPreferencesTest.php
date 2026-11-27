<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\Message;
use App\Models\User;
use App\Models\Waitlist;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewMessageReceived;
use App\Notifications\WaitlistSpotAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_all_notifications_enabled(): void
    {
        // fresh() SENGAJA dipakai (bukan cek $user langsung) -- default
        // kolom di DB tidak otomatis terefleksi ke instance model yang
        // baru saja dibuat lewat create(), cuma ke baris sungguhan di DB.
        // Yang mau dites di sini memang perilaku baris DB-nya, bukan
        // instance PHP di memori.
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->notify_bookings);
        $this->assertTrue($user->notify_messages);
        $this->assertTrue($user->notify_waitlist);
    }

    public function test_api_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/notification-preferences', [
            'notify_bookings' => true,
            'notify_messages' => false,
            'notify_waitlist' => false,
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertTrue($user->notify_bookings);
        $this->assertFalse($user->notify_messages);
        $this->assertFalse($user->notify_waitlist);
    }

    public function test_web_can_update_notification_preferences(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Checkbox yang tidak dicentang tidak ikut terkirim di form HTML --
        // di sini cuma notify_bookings yang dikirim (dianggap dicentang).
        $response = $this->actingAs($user)->post('/notifikasi/preferensi', [
            'notify_bookings' => '1',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->notify_bookings);
        $this->assertFalse($user->notify_messages);
        $this->assertFalse($user->notify_waitlist);
    }

    public function test_muting_messages_prevents_the_notification_from_being_sent(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'owner', 'notify_messages' => false]);
        $tenant = User::factory()->create(['role' => 'user']);

        $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
            'body' => 'Halo, masih kosong?',
        ])->assertCreated();

        Notification::assertNotSentTo($owner, NewMessageReceived::class);
    }

    public function test_muting_bookings_prevents_the_notification_from_being_sent(): void
    {
        Notification::fake();
        $tenant = User::factory()->create(['notify_bookings' => false]);
        $kos = Kos::factory()->create();
        $booking = Booking::create([
            'user_id' => $tenant->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $booking->update(['status' => 'confirmed']);
        $tenant->notify(new BookingStatusChanged($booking->fresh()));

        Notification::assertNotSentTo($tenant, BookingStatusChanged::class);
    }

    public function test_muting_waitlist_prevents_the_notification_from_being_sent(): void
    {
        Notification::fake();
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $waiter = User::factory()->create(['notify_waitlist' => false]);
        Waitlist::create(['user_id' => $waiter->id, 'kos_id' => $kos->id]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDays(2),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $booking->update(['status' => 'cancelled']);

        Notification::assertNotSentTo($waiter, WaitlistSpotAvailable::class);
        // Tetap ditandai sudah "diberi tahu" walau notifikasinya tidak
        // benar-benar terkirim -- supaya tidak dicoba lagi berulang-ulang
        // tiap kali ada perubahan lain di kos yang sama.
        $this->assertNotNull(Waitlist::first()->notified_at);
    }
}
