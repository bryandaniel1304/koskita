<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KosDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_by_price_ascending(): void
    {
        Kos::factory()->create(['price' => 2000000, 'name' => 'Mahal']);
        Kos::factory()->create(['price' => 1000000, 'name' => 'Murah']);

        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?sort=price_asc');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame('Murah', $names->first());
    }

    public function test_sort_by_price_descending(): void
    {
        Kos::factory()->create(['price' => 2000000, 'name' => 'Mahal']);
        Kos::factory()->create(['price' => 1000000, 'name' => 'Murah']);

        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?sort=price_desc');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame('Mahal', $names->first());
    }

    public function test_kos_show_includes_similar_koses(): void
    {
        $kos = Kos::factory()->create(['price' => 1500000, 'location' => 'Karawaci']);
        $similar = Kos::factory()->create(['price' => 1550000, 'location' => 'Karawaci']);
        Kos::factory()->create(['price' => 9000000, 'location' => 'BSD']); // jauh beda, tidak masuk

        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk();
        $similarIds = collect($response->json('similar'))->pluck('id');
        $this->assertContains($similar->id, $similarIds);
    }

    public function test_owner_response_badge_reflects_fast_replies(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);
        $kos = Kos::factory()->create(['owner_id' => $owner->id]);

        Message::create(['sender_id' => $tenant->id, 'receiver_id' => $owner->id, 'body' => 'Halo']);
        Message::create(['sender_id' => $owner->id, 'receiver_id' => $tenant->id, 'body' => 'Halo juga']);

        $response = $this->actingAs($tenant, 'sanctum')->getJson("/api/kos/{$kos->id}");

        $response->assertOk();
        $this->assertSame('Biasanya balas dalam < 1 jam', $response->json('kos.owner_response_badge'));
    }

    public function test_favoriting_snapshots_price_and_rooms(): void
    {
        $user = User::factory()->create();
        $kos = Kos::factory()->create(['price' => 1200000, 'total_rooms' => 5]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/kos/{$kos->id}/rate", [
            'is_favorite' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $user->id,
            'kos_id' => $kos->id,
            'favorited_price_snapshot' => 1200000,
            'favorited_rooms_snapshot' => 5,
        ]);
    }
}
