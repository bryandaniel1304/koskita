<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\KosReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerReviewReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function verifiedOwner(): User
    {
        return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    }

    public function test_owner_can_reply_to_review_on_own_kos(): void
    {
        $owner = $this->verifiedOwner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $review = KosReview::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'rating' => 4,
            'comment' => 'Lumayan bersih.',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/owner/reviews/{$review->id}/reply", [
            'reply' => 'Terima kasih atas ulasannya!',
        ]);

        $response->assertOk();
        $review->refresh();
        $this->assertSame('Terima kasih atas ulasannya!', $review->owner_reply);
        $this->assertNotNull($review->owner_replied_at);
    }

    public function test_owner_cannot_reply_to_review_on_other_owners_kos(): void
    {
        $owner = $this->verifiedOwner();
        $otherOwner = $this->verifiedOwner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);
        $review = KosReview::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/owner/reviews/{$review->id}/reply", [
            'reply' => 'Coba bajak balasan.',
        ]);

        $response->assertStatus(404);
        $this->assertNull($review->fresh()->owner_reply);
    }

    public function test_empty_reply_clears_existing_reply(): void
    {
        $owner = $this->verifiedOwner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);
        $review = KosReview::create([
            'user_id' => User::factory()->create()->id,
            'kos_id' => $kos->id,
            'rating' => 5,
            'owner_reply' => 'Balasan lama',
            'owner_replied_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/owner/reviews/{$review->id}/reply", [
            'reply' => '',
        ]);

        $response->assertOk();
        $review->refresh();
        $this->assertNull($review->owner_reply);
        $this->assertNull($review->owner_replied_at);
    }
}
