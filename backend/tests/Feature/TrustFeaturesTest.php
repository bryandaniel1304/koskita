<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Batch fitur "kepercayaan & keandalan": lapor/flag, waitlist, pengingat
 * sewa, alert favorit, dan verifikasi pemilik/kos.
 */
class TrustFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_report_a_kos(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reports', [
            'type' => 'kos',
            'id' => $kos->id,
            'reason' => 'Foto tidak sesuai',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reports', [
            'reportable_type' => Kos::class,
            'reportable_id' => $kos->id,
            'reporter_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_can_report_a_review(): void
    {
        $user = User::factory()->create();
        $review = KosReview::create(['user_id' => User::factory()->create()->id, 'kos_id' => Kos::factory()->create()->id, 'rating' => 1]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/reports', [
            'type' => 'review',
            'id' => $review->id,
            'reason' => 'Spam',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reports', ['reportable_type' => KosReview::class, 'reportable_id' => $review->id]);
    }

    public function test_user_can_join_and_leave_waitlist(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/waitlist")->assertOk();
        $this->assertDatabaseHas('waitlists', ['user_id' => $user->id, 'kos_id' => $kos->id]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/kos/{$kos->id}/waitlist")->assertOk();
        $this->assertDatabaseCount('waitlists', 0);
    }

    public function test_waitlist_notification_fires_once_room_available(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['total_rooms' => 2]);
        Waitlist::create(['user_id' => $user->id, 'kos_id' => $kos->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'waitlist_available']);
        $this->assertNotNull(Waitlist::first()->notified_at);

        // Panggilan kedua tidak mengulang notifikasi yang sama.
        $second = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');
        $types = collect($second->json('notifications'))->pluck('type');
        $this->assertFalse($types->contains('waitlist_available'));
    }

    public function test_rent_reminder_appears_within_three_days_of_due_date(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['name' => 'Kos Jatuh Tempo']);
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonths(1)->addDays(2), // jatuh tempo bulanan berikutnya ~2 hari lagi
            'duration_months' => 6,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonFragment(['type' => 'rent_reminder']);
    }

    public function test_rent_reminder_does_not_appear_after_contract_ends(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create();
        Booking::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'start_date' => now()->subMonths(6)->addDays(1),
            'duration_months' => 1, // sudah lewat masa sewa 1 bulan
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $types = collect($response->json('notifications'))->pluck('type');
        $this->assertFalse($types->contains('rent_reminder'));
    }

    public function test_favorite_price_drop_alert(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['price' => 1000000]);
        UserInteraction::create([
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'is_favorite' => true,
            'favorited_price_snapshot' => 1500000,
            'favorited_rooms_snapshot' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertJsonFragment(['type' => 'price_drop']);
    }

    public function test_owner_can_submit_verification_document(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/owner/verification', [
            'document' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $owner->refresh();
        $this->assertSame('pending', $owner->owner_verification_status);
        $this->assertNotNull($owner->owner_verification_document);
    }

    public function test_admin_can_approve_owner_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner', 'owner_verification_status' => 'pending']);

        $response = $this->actingAs($admin)->put("/admin/users/{$owner->id}/verifikasi", ['decision' => 'approved']);

        $response->assertRedirect();
        $owner->refresh();
        $this->assertTrue($owner->isVerifiedOwner());
    }

    public function test_owner_can_upload_qris(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/owner/qris', [
            'qris' => UploadedFile::fake()->create('qris.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($owner->fresh()->qris_image_path);
    }

    public function test_admin_can_toggle_kos_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kos = Kos::factory()->create();

        $this->actingAs($admin)->put("/admin/koses/{$kos->id}/verifikasi")->assertRedirect();
        $this->assertNotNull($kos->fresh()->verified_at);

        $this->actingAs($admin)->put("/admin/koses/{$kos->id}/verifikasi")->assertRedirect();
        $this->assertNull($kos->fresh()->verified_at);
    }
}
