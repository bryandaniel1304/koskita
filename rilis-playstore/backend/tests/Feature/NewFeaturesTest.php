<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test untuk batch fitur pelengkap frontend (filter budget/fasilitas, rating
 * breakdown, foto ulasan, notifikasi in-app, analitik mingguan pemilik).
 */
class NewFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_kos_list_can_be_filtered_by_budget_range(): void
    {
        $user = User::factory()->create();
        Kos::factory()->create(['price' => 1000000]);
        $inRange = Kos::factory()->create(['price' => 2500000]);
        Kos::factory()->create(['price' => 5000000]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?budget_min=2000000&budget_max=3000000');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertCount(1, $ids);
    }

    public function test_kos_list_can_be_filtered_by_facilities(): void
    {
        $user = User::factory()->create();
        $ac = Facility::create(['name' => 'AC']);
        $wifi = Facility::create(['name' => 'WiFi']);

        $withBoth = Kos::factory()->create();
        $withBoth->facilities()->sync([$ac->id, $wifi->id]);

        $withOne = Kos::factory()->create();
        $withOne->facilities()->sync([$ac->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/kos?' . http_build_query(['facilities' => [$ac->id, $wifi->id]]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($withBoth->id));
        $this->assertFalse($ids->contains($withOne->id));
    }

    public function test_kos_json_includes_rating_breakdown(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        KosReview::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'rating' => 5]);
        KosReview::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'rating' => 5]);
        KosReview::create(['user_id' => User::factory()->create()->id, 'kos_id' => $kos->id, 'rating' => 3]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk();
        $response->assertJsonPath('kos.rating_breakdown.5', 2);
        $response->assertJsonPath('kos.rating_breakdown.3', 1);
        $response->assertJsonPath('kos.rating_breakdown.1', 0);
    }

    public function test_review_can_be_submitted_with_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonth(),
            'duration_months' => 3,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')->post("/api/kos/{$kos->id}/reviews", [
            'rating' => 5,
            'comment' => 'Mantap!',
            // ->create() (bukan ->image()) -- lingkungan tes ini tidak
            // punya ekstensi GD PHP yang dibutuhkan fake()->image() untuk
            // benar-benar merender gambar tiruan. ->create() dengan MIME
            // type gambar cukup untuk lolos validasi 'image' bawaan
            // Laravel tanpa perlu GD.
            'photo' => UploadedFile::fake()->create('kamar.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(201);
        $review = KosReview::where('user_id', $user->id)->where('kos_id', $kos->id)->firstOrFail();
        $this->assertNotNull($review->photo_path);
        Storage::disk('public')->assertExists($review->photo_path);
    }

    public function test_tenant_notifications_reflect_their_booking_status_changes(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['name' => 'Kos Notifikasi']);
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDay(),
            'duration_months' => 3,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'booking_confirmed']);
    }

    public function test_owner_notifications_include_pending_bookings_and_new_reviews(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $tenant = User::factory()->create();

        Booking::create([
            'user_id' => $tenant->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDay(),
            'duration_months' => 2,
            'status' => 'pending',
        ]);
        KosReview::create(['user_id' => $tenant->id, 'kos_id' => $kos->id, 'rating' => 4]);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'booking_pending']);
        $response->assertJsonFragment(['type' => 'new_review']);
    }

    public function test_owner_can_view_weekly_analytics_for_their_kos(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        Booking::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'start_date' => now()->addDay(),
            'duration_months' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/owner/koses/{$kos->id}/analytics");

        $response->assertOk();
        $response->assertJsonCount(8, 'weeks');
        $totalBookings = collect($response->json('weeks'))->sum('bookings');
        $this->assertSame(1, $totalBookings);
    }

    public function test_owner_cannot_view_analytics_for_kos_they_do_not_own(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/owner/koses/{$kos->id}/analytics");

        $response->assertStatus(404);
    }
}
