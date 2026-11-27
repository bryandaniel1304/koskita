<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manajemen kamar: total_rooms di kos, occupied_rooms dihitung LIVE dari
 * booking berstatus "confirmed" (bukan kolom tersimpan) -- tes ini
 * membuktikan angkanya benar dan mencegah overbooking di kedua sisi
 * (penyewa mengajukan baru, pemilik mengonfirmasi).
 */
class RoomAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupied_and_available_rooms_reflect_confirmed_bookings_only(): void
    {
        $kos = Kos::factory()->create(['total_rooms' => 2]);
        $tenantA = User::factory()->create();
        $tenantB = User::factory()->create();

        Booking::create([
            'user_id' => $tenantA->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'confirmed',
        ]);
        Booking::create([
            'user_id' => $tenantB->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'pending',
        ]);

        $fresh = Kos::findOrFail($kos->id);

        $this->assertEquals(1, $fresh->occupied_rooms); // cuma yang "confirmed" dihitung, bukan "pending"
        $this->assertEquals(1, $fresh->available_rooms);
    }

    public function test_tenant_cannot_book_a_fully_occupied_kos(): void
    {
        $kos = Kos::factory()->create(['total_rooms' => 1]);
        $existingTenant = User::factory()->create();
        Booking::create([
            'user_id' => $existingTenant->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'confirmed',
        ]);

        $newTenant = User::factory()->create();
        $response = $this->actingAs($newTenant, 'sanctum')->postJson('/api/bookings', [
            'kos_id' => $kos->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 2,
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_cannot_confirm_booking_when_kos_is_full(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'total_rooms' => 1]);

        $tenantA = User::factory()->create();
        Booking::create([
            'user_id' => $tenantA->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'confirmed',
        ]);

        $tenantB = User::factory()->create();
        $pendingBooking = Booking::create([
            'user_id' => $tenantB->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'pending',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/bookings/{$pendingBooking->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('bookings', ['id' => $pendingBooking->id, 'status' => 'pending']);
    }

    public function test_owner_can_confirm_booking_when_room_is_available(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'total_rooms' => 2]);

        $tenant = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $tenant->id, 'kos_id' => $kos->id,
            'start_date' => now()->addDay(), 'duration_months' => 3, 'status' => 'pending',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/bookings/{$booking->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }
}
