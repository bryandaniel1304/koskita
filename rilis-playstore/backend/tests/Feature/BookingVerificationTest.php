<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking adalah aksi sensitif (skripsi: keamanan akun) -- wajib email
 * terverifikasi dulu. Tes ini yang membuktikan middleware 'verified'
 * benar-benar mengunci aksi ini, bukan cuma tampil di dokumentasi.
 */
class BookingVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_create_booking(): void
    {
        $user = User::factory()->unverified()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'kos_id' => $kos->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 3,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_verified_user_can_create_booking(): void
    {
        $user = User::factory()->create(); // factory default: email_verified_at = now()
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/bookings', [
            'kos_id' => $kos->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'duration_months' => 3,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'kos_id' => $kos->id, 'status' => 'pending']);
    }
}
