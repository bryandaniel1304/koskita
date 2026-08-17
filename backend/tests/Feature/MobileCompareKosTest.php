<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCompareKosTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparing_two_or_more_kos_returns_them_in_the_requested_order(): void
    {
        $user = User::factory()->create();
        $a = Kos::factory()->create();
        $b = Kos::factory()->create();
        $c = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/compare?ids={$c->id},{$a->id},{$b->id}");

        $response->assertOk();
        $ids = collect($response->json('koses'))->pluck('id')->all();
        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_comparing_fewer_than_two_kos_is_rejected(): void
    {
        $user = User::factory()->create();
        $a = Kos::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/compare?ids={$a->id}");

        $response->assertStatus(422);
    }

    public function test_comparing_more_than_three_kos_returns_them_all(): void
    {
        // Tidak ada batas bisnis lagi -- 10 kos sekaligus harus tetap
        // muncul semua, bukan cuma 3 yang pertama.
        $user = User::factory()->create();
        $ids = Kos::factory()->count(10)->create()->pluck('id')->implode(',');

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/compare?ids={$ids}");

        $response->assertOk();
        $this->assertCount(10, $response->json('koses'));
    }

    public function test_comparing_is_capped_at_fifty_kos_as_an_anti_abuse_ceiling(): void
    {
        $user = User::factory()->create();
        $ids = Kos::factory()->count(55)->create()->pluck('id')->implode(',');

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/kos/compare?ids={$ids}");

        $response->assertOk();
        $this->assertCount(50, $response->json('koses'));
    }
}
