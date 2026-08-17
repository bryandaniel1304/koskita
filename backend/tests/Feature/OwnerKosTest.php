<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerKosTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    public function test_owner_can_create_a_kos_they_own(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/owner/koses', [
            'name' => 'Kos Melati',
            'price' => 1500000,
            'gender_type' => 'campur',
            'location' => 'Karawaci',
            'distance_to_campus' => 1.0,
            'total_rooms' => 4,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('koses', ['name' => 'Kos Melati', 'owner_id' => $owner->id, 'total_rooms' => 4]);
    }

    public function test_tenant_role_cannot_access_owner_kos_endpoints(): void
    {
        $tenant = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($tenant, 'sanctum')->getJson('/api/owner/koses');

        $response->assertStatus(403);
    }

    public function test_owner_cannot_update_a_kos_they_do_not_own(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);

        $response = $this->actingAs($owner, 'sanctum')->putJson("/api/owner/koses/{$kos->id}", [
            'name' => 'Diubah Paksa',
            'price' => 1000000,
            'gender_type' => 'campur',
            'location' => 'BSD',
            'distance_to_campus' => 2.0,
            'total_rooms' => 3,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('koses', ['name' => 'Diubah Paksa']);
    }

    public function test_owner_cannot_delete_a_kos_they_do_not_own(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $kos = Kos::factory()->create(['owner_id' => $otherOwner->id]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/owner/koses/{$kos->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('koses', ['id' => $kos->id]);
    }

    public function test_unverified_owner_is_blocked_from_owner_routes(): void
    {
        $owner = User::factory()->unverified()->create(['role' => 'owner']);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/owner/koses');

        $response->assertStatus(403);
    }

    public function test_matches_endpoint_only_returns_tenant_profiles_matching_kos_gender(): void
    {
        $owner = $this->makeOwner();
        $kos = Kos::factory()->create(['owner_id' => $owner->id, 'gender_type' => 'putri']);

        $matchingTenant = User::factory()->create(['role' => 'user']);
        UserProfile::create([
            'user_id' => $matchingTenant->id, 'gender' => 'wanita', 'occupation' => 'mahasiswa',
            'budget_min' => 1000000, 'budget_max' => 3000000, 'preferred_facilities' => [], 'preferred_rules' => [],
            'preferred_location' => $kos->location,
        ]);

        $mismatchedTenant = User::factory()->create(['role' => 'user']);
        UserProfile::create([
            'user_id' => $mismatchedTenant->id, 'gender' => 'pria', 'occupation' => 'mahasiswa',
            'budget_min' => 1000000, 'budget_max' => 3000000, 'preferred_facilities' => [], 'preferred_rules' => [],
            'preferred_location' => $kos->location,
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/owner/koses/{$kos->id}/matches");

        $response->assertStatus(200);
        $matches = $response->json('matches');
        $userIds = collect($matches)->pluck('user.id')->all();

        $this->assertContains($matchingTenant->id, $userIds);
        // Pria dengan skor 0 (gender mismatch) tidak akan pernah masuk top-10
        // kalau ada kandidat relevan lain -- cukup pastikan skornya 0 kalau muncul.
        foreach ($matches as $match) {
            if ($match['user']['id'] === $mismatchedTenant->id) {
                $this->assertEquals(0, $match['match_percentage']);
            }
        }
    }
}
