<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KosPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_a_paginated_envelope_with_twenty_per_page(): void
    {
        Kos::factory()->count(25)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'last_page', 'total', 'per_page']);
        $this->assertCount(20, $response->json('data'));
        $this->assertSame(25, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));
    }

    public function test_second_page_returns_the_remaining_kos(): void
    {
        Kos::factory()->count(25)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?page=2');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(2, $response->json('current_page'));
    }

    public function test_pagination_preserves_active_filters(): void
    {
        Kos::factory()->count(25)->create(['gender_type' => 'putra']);
        Kos::factory()->count(3)->create(['gender_type' => 'putri']);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/kos?gender_type=putri');

        $response->assertOk();
        $this->assertSame(3, $response->json('total'));
    }
}
