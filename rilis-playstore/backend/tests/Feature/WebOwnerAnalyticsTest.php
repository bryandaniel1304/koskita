<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebOwnerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_owner_can_view_analytics_page(): void
    {
        $owner = $this->owner();
        $response = $this->actingAs($owner)->get('/pemilik/analitik');
        $response->assertOk();
        $response->assertSee('Corong Konversi');
    }

    public function test_owner_can_view_settings_page(): void
    {
        $owner = $this->owner();
        $response = $this->actingAs($owner)->get('/pemilik/pengaturan');
        $response->assertOk();
        $response->assertSee('Verifikasi Pemilik');
    }

    public function test_owner_can_bulk_mark_bookings_paid(): void
    {
        $owner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $b1 = Booking::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'start_date' => now(), 'duration_months' => 2, 'status' => 'confirmed']);
        $b2 = Booking::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'start_date' => now(), 'duration_months' => 2, 'status' => 'confirmed']);

        $response = $this->actingAs($owner)->post('/pemilik/booking/tandai-lunas-massal', [
            'booking_ids' => [$b1->id, $b2->id],
        ]);

        $response->assertRedirect();
        $this->assertSame('paid', $b1->fresh()->payment_status);
        $this->assertSame('paid', $b2->fresh()->payment_status);
    }

    public function test_bulk_mark_paid_cannot_touch_other_owners_bookings(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);
        $booking = Booking::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'start_date' => now(), 'duration_months' => 2, 'status' => 'confirmed']);

        $this->actingAs($owner)->post('/pemilik/booking/tandai-lunas-massal', ['booking_ids' => [$booking->id]]);

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_owner_can_upload_qris_via_web(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $response = $this->actingAs($owner)->post('/pemilik/qris', [
            'qris' => UploadedFile::fake()->create('qris.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $this->assertNotNull($owner->fresh()->qris_image_path);
    }
}
