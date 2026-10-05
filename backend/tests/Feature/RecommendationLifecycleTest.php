<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siklus status rekomendasi seorang penyewa lewat endpoint yang dipakai
 * aplikasi & panel admin (bukan memanggil RecommendationService langsung):
 * UC-19 memberi rating memindahkan cold-start -> warm-start, UC-13 reset
 * riwayat mengembalikannya ke cold-start, dan UC-45 menampilkan rincian
 * Skor_CB/Skor_CF/Skor_hybrid kepada Master Admin.
 */
class RecommendationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tenant(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => 'pria',
            'occupation' => 'mahasiswa',
            'budget_min' => 1000000,
            'budget_max' => 3000000,
            'preferred_facilities' => [],
            'preferred_rules' => [],
            'preferred_location' => 'Karawaci',
        ]);

        return $user;
    }

    public function test_rating_a_kos_moves_tenant_from_cold_start_to_warm_start(): void
    {
        $tenant = $this->tenant();
        $koses = Kos::factory()->count(3)->create(['gender_type' => 'campur']);

        $this->actingAs($tenant, 'sanctum')->getJson('/api/recommendations')
            ->assertOk()
            ->assertJson(['is_cold_start' => true, 'alpha' => 1, 'rating_count' => 0]);

        $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$koses[0]->id}/rate", ['rating' => 4])
            ->assertOk();

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $tenant->id,
            'kos_id' => $koses[0]->id,
            'rating' => 4,
        ]);

        $this->actingAs($tenant, 'sanctum')->getJson('/api/recommendations')
            ->assertOk()
            ->assertJson(['is_cold_start' => false, 'alpha' => 0.6, 'rating_count' => 1]);
    }

    public function test_rating_outside_one_to_five_is_rejected(): void
    {
        $tenant = $this->tenant();
        $kos = Kos::factory()->create();

        foreach ([0, 6] as $invalid) {
            $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$kos->id}/rate", ['rating' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('rating');
        }

        $this->assertDatabaseCount('user_interactions', 0);
    }

    public function test_rating_the_same_kos_again_updates_instead_of_duplicating(): void
    {
        $tenant = $this->tenant();
        $kos = Kos::factory()->create();

        $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$kos->id}/rate", ['rating' => 2])->assertOk();
        $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$kos->id}/rate", ['rating' => 5])->assertOk();

        $this->assertSame(1, UserInteraction::where('user_id', $tenant->id)->count());
        $this->assertSame(5, (int) UserInteraction::where('user_id', $tenant->id)->value('rating'));
    }

    public function test_resetting_interactions_returns_tenant_to_cold_start_without_touching_others(): void
    {
        $tenant = $this->tenant();
        $other = $this->tenant();
        $koses = Kos::factory()->count(2)->create(['gender_type' => 'campur']);

        $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$koses[0]->id}/rate", ['rating' => 5])->assertOk();
        $this->actingAs($tenant, 'sanctum')->postJson("/api/kos/{$koses[1]->id}/rate", ['is_favorite' => true])->assertOk();
        UserInteraction::create(['user_id' => $other->id, 'kos_id' => $koses[0]->id, 'rating' => 3]);

        $this->actingAs($tenant, 'sanctum')->postJson('/api/profile/reset-interactions')->assertOk();

        $this->assertSame(0, UserInteraction::where('user_id', $tenant->id)->count());
        $this->assertSame(1, UserInteraction::where('user_id', $other->id)->count());

        $this->actingAs($tenant, 'sanctum')->getJson('/api/recommendations')
            ->assertOk()
            ->assertJson(['is_cold_start' => true, 'alpha' => 1, 'rating_count' => 0]);
    }

    public function test_master_admin_sees_score_breakdown_that_follows_the_tenant_status(): void
    {
        $master = User::factory()->create(['role' => 'admin', 'is_master_admin' => true]);
        $tenant = $this->tenant();
        $kos = Kos::factory()->create(['gender_type' => 'campur', 'name' => 'Kos Uji Rincian Skor']);

        $this->actingAs($master)->get("/admin/users/{$tenant->id}")
            ->assertOk()
            ->assertSee('Content-Based Murni (α = 1.0)')
            ->assertSee('Score CB')
            ->assertSee('Score CF')
            ->assertSee('Score Hybrid')
            ->assertSee('Kos Uji Rincian Skor');

        UserInteraction::create(['user_id' => $tenant->id, 'kos_id' => $kos->id, 'rating' => 4]);

        $this->actingAs($master)->get("/admin/users/{$tenant->id}")
            ->assertOk()
            ->assertSee('Hybrid CB+CF (α = 0.6)')
            ->assertDontSee('Content-Based Murni (α = 1.0)');
    }

    public function test_regular_admin_and_tenant_cannot_open_the_score_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tenant = $this->tenant();

        $this->actingAs($admin)->get("/admin/users/{$tenant->id}")->assertForbidden();
        $this->actingAs($tenant)->get("/admin/users/{$tenant->id}")->assertRedirect('/login');
    }
}
