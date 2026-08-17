<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebKosTrustFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_toggle_waitlist_via_web(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $this->actingAs($user)->post("/kos/{$kos->id}/waitlist")->assertRedirect();
        $this->assertDatabaseHas('waitlists', ['user_id' => $user->id, 'kos_id' => $kos->id]);

        $this->actingAs($user)->post("/kos/{$kos->id}/waitlist")->assertRedirect();
        $this->assertDatabaseCount('waitlists', 0);
    }

    public function test_user_can_report_kos_via_web(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user)->post("/kos/{$kos->id}/lapor", ['reason' => 'Foto tidak sesuai']);

        $response->assertRedirect();
        $this->assertDatabaseHas('reports', ['reportable_id' => $kos->id, 'reporter_id' => $user->id]);
    }

    public function test_user_can_view_printable_receipt_for_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['name' => 'Kos Bukti']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->get("/booking-saya/{$booking->id}/bukti");

        $response->assertOk();
        $response->assertSee('Kos Bukti');
    }

    public function test_user_cannot_view_receipt_for_pending_booking(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        $booking = Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now(),
            'duration_months' => 3,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get("/booking-saya/{$booking->id}/bukti");

        $response->assertStatus(404);
    }

    public function test_user_cannot_view_another_users_receipt(): void
    {
        $owner = User::factory()->create();
        $kos = Kos::factory()->create();
        $booking = Booking::create([
            'user_id' => $owner->id,
            'kos_id' => $kos->id,
            'start_date' => now(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);
        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->get("/booking-saya/{$booking->id}/bukti");

        $response->assertStatus(404);
    }

    public function test_kos_index_can_be_sorted_by_price(): void
    {
        Kos::factory()->create(['price' => 2000000, 'name' => 'Mahal']);
        Kos::factory()->create(['price' => 1000000, 'name' => 'Murah']);

        $response = $this->get('/kos?sort=harga_termurah');

        $response->assertOk();
        $response->assertSeeInOrder(['Murah', 'Mahal']);
    }
}
