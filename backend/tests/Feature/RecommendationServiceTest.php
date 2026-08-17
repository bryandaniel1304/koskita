<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserProfile;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembuktian skripsi subbab 2.9.9 -- strategi switching-weighted: cold-start
 * (belum ada rating) harus murni Content-Based (alpha=1.0), warm-start
 * (sudah ada rating) pakai alpha production (0.6). Juga membuktikan hard
 * constraint gender (kos putri tidak boleh muncul untuk profil pria, dst).
 */
class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUserWithProfile(array $profileOverrides = []): User
    {
        $user = User::factory()->create(['role' => 'user']);
        UserProfile::create(array_merge([
            'user_id' => $user->id,
            'gender' => 'pria',
            'occupation' => 'mahasiswa',
            'budget_min' => 1000000,
            'budget_max' => 3000000,
            'preferred_facilities' => [],
            'preferred_rules' => [],
            'preferred_location' => 'Karawaci',
        ], $profileOverrides));

        return $user;
    }

    public function test_cold_start_user_gets_alpha_one(): void
    {
        $user = $this->makeUserWithProfile();
        Kos::factory()->count(3)->create(['gender_type' => 'campur']);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $this->assertTrue($result['is_cold_start']);
        $this->assertEquals(1.0, $result['alpha']);
    }

    public function test_warm_start_user_gets_production_alpha(): void
    {
        $user = $this->makeUserWithProfile();
        $koses = Kos::factory()->count(3)->create(['gender_type' => 'campur']);

        UserInteraction::create([
            'user_id' => $user->id,
            'kos_id' => $koses->first()->id,
            'rating' => 5,
        ]);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $this->assertFalse($result['is_cold_start']);
        $this->assertEquals(0.6, $result['alpha']);
    }

    public function test_gender_mismatch_scores_zero(): void
    {
        $user = $this->makeUserWithProfile(['gender' => 'pria']);
        $kosPutri = Kos::factory()->create(['gender_type' => 'putri']);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $recommendation = collect($result['recommendations'])->firstWhere(fn ($r) => $r['kos']->id === $kosPutri->id);

        $this->assertNotNull($recommendation);
        $this->assertEquals(0.0, $recommendation['score_cb']);
    }

    public function test_recommendations_are_sorted_descending_by_hybrid_score(): void
    {
        $user = $this->makeUserWithProfile();
        Kos::factory()->count(5)->create(['gender_type' => 'campur']);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);
        $scores = collect($result['recommendations'])->pluck('score_hybrid')->all();

        $sorted = $scores;
        rsort($sorted);
        $this->assertEquals($sorted, $scores);
    }
}
