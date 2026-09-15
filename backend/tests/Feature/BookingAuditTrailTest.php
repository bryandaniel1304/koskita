<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test nomor pengajuan sewa & jejak audit perubahan status booking.
 * Penekanannya: pencatatan terjadi di level model (Booking::booted), jadi
 * SEMUA jalur perubahan status ikut tercatat -- bukan cuma panel admin.
 */
class BookingAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'kos_id' => Kos::factory()->create()->id,
            'start_date' => now()->addDays(7),
            'duration_months' => 3,
            'status' => 'pending',
        ], $attributes));
    }

    public function test_booking_gets_unique_code_on_creation(): void
    {
        $first = $this->makeBooking();
        $second = $this->makeBooking();

        $this->assertMatchesRegularExpression('/^KK-\d{6}-[A-Z2-9]{4}$/', $first->code);
        $this->assertNotSame($first->code, $second->code);
    }

    public function test_code_cannot_be_overridden_from_request_input(): void
    {
        // `code` di luar $fillable -- nomor pengajuan harus selalu dari sistem.
        $booking = $this->makeBooking(['code' => 'KK-999999-XXXX']);

        $this->assertNotSame('KK-999999-XXXX', $booking->code);
    }

    public function test_admin_status_change_is_logged_with_actor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->makeBooking();

        $this->actingAs($admin)->put("/admin/bookings/{$booking->id}", [
            'status' => 'confirmed',
            'admin_note' => 'Kamar masih tersedia.',
        ])->assertRedirect();

        $log = $booking->activityLogs()->first();
        $this->assertSame('status', $log->field);
        $this->assertSame('pending', $log->from_value);
        $this->assertSame('confirmed', $log->to_value);
        $this->assertSame($admin->id, $log->changed_by);
        $this->assertSame('admin', $log->actor_role);
        $this->assertSame('Kamar masih tersedia.', $log->note);
    }

    public function test_master_admin_is_distinguished_from_regular_admin_in_log(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'is_master_admin' => true]);
        $booking = $this->makeBooking();

        $this->actingAs($master)->put("/admin/bookings/{$booking->id}", ['status' => 'rejected'])->assertRedirect();

        $this->assertSame('master_admin', $booking->activityLogs()->first()->actor_role);
    }

    public function test_tenant_cancellation_is_logged_too(): void
    {
        $tenant = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $booking = $this->makeBooking(['user_id' => $tenant->id]);

        $this->actingAs($tenant, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel")->assertOk();

        $log = $booking->activityLogs()->first();
        $this->assertSame('cancelled', $log->to_value);
        $this->assertSame($tenant->id, $log->changed_by);
        $this->assertSame('user', $log->actor_role);
    }

    public function test_payment_status_change_is_logged_as_separate_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->makeBooking();

        $this->actingAs($admin);
        $booking->update(['payment_status' => 'paid', 'paid_at' => now()]);

        $log = $booking->activityLogs()->first();
        $this->assertSame('payment_status', $log->field);
        $this->assertSame('unpaid', $log->from_value);
        $this->assertSame('paid', $log->to_value);
    }

    public function test_unchanged_status_does_not_create_log_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->makeBooking();

        // Admin klik Simpan tanpa mengubah status -- riwayat jangan sampai
        // penuh baris "pending -> pending" yang tidak berarti apa-apa.
        $this->actingAs($admin)->put("/admin/bookings/{$booking->id}", ['status' => 'pending'])->assertRedirect();

        $this->assertSame(0, $booking->activityLogs()->count());
    }

    public function test_history_survives_actor_account_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Lama']);
        $booking = $this->makeBooking();

        $this->actingAs($admin)->put("/admin/bookings/{$booking->id}", ['status' => 'confirmed'])->assertRedirect();
        $admin->delete();

        $log = $booking->activityLogs()->first();
        $this->assertNull($log->changed_by);
        $this->assertSame('Admin Lama', $log->actorLabel());
    }

    public function test_admin_detail_page_shows_code_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->makeBooking();

        $this->actingAs($admin)->put("/admin/bookings/{$booking->id}", ['status' => 'confirmed'])->assertRedirect();

        $this->actingAs($admin)->get("/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Riwayat Perubahan')
            ->assertSee('Dikonfirmasi')
            ->assertSee($admin->name);
    }
}
