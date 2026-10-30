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
 * (sudah ada rating) pakai alpha production (0.6). Juga membuktikan batasan
 * mutlak gender, area, dan anggaran: kos yang melanggarnya tidak boleh
 * muncul di rekomendasi, bahkan saat warm-start.
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

    protected function matchingKos(int $count, array $overrides = [])
    {
        return Kos::factory()->count($count)->create(array_merge([
            'location' => 'Karawaci',
            'price' => 2000000,
            'gender_type' => 'campur',
        ], $overrides));
    }

    protected function recommendedIds(User $user): array
    {
        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        return collect($result['recommendations'])->pluck('kos.id')->all();
    }

    public function test_cold_start_user_gets_alpha_one(): void
    {
        $user = $this->makeUserWithProfile();
        $this->matchingKos(3);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $this->assertTrue($result['is_cold_start']);
        $this->assertEquals(1.0, $result['alpha']);
    }

    public function test_warm_start_user_gets_production_alpha(): void
    {
        $user = $this->makeUserWithProfile();
        $koses = $this->matchingKos(3);

        UserInteraction::create([
            'user_id' => $user->id,
            'kos_id' => $koses->first()->id,
            'rating' => 5,
        ]);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $this->assertFalse($result['is_cold_start']);
        $this->assertEquals(0.6, $result['alpha']);
    }

    public function test_gender_mismatch_is_excluded(): void
    {
        $user = $this->makeUserWithProfile(['gender' => 'pria']);
        $kosPutri = $this->matchingKos(1, ['gender_type' => 'putri'])->first();

        $ids = $this->recommendedIds($user);

        $this->assertNotContains($kosPutri->id, $ids);
    }

    /**
     * Kasus yang dilaporkan dari uji coba: preferensi Karawaci Rp4-6 juta
     * tidak boleh memunculkan kos di BSD City maupun kos di bawah Rp4 juta.
     */
    public function test_only_kos_in_preferred_area_and_budget_are_recommended(): void
    {
        $user = $this->makeUserWithProfile(['preferred_location' => 'Karawaci', 'budget_min' => 4000000, 'budget_max' => 6000000]);
        $fit = Kos::factory()->create(['location' => 'Karawaci', 'price' => 4500000, 'gender_type' => 'campur']);
        $edgeMin = Kos::factory()->create(['location' => 'Karawaci', 'price' => 4000000, 'gender_type' => 'campur']);
        $edgeMax = Kos::factory()->create(['location' => 'Karawaci', 'price' => 6000000, 'gender_type' => 'putra']);
        $bsd = Kos::factory()->create(['location' => 'BSD City', 'price' => 5000000, 'gender_type' => 'campur']);
        $cheap = Kos::factory()->create(['location' => 'Karawaci', 'price' => 3500000, 'gender_type' => 'campur']);
        $pricey = Kos::factory()->create(['location' => 'Karawaci', 'price' => 6018000, 'gender_type' => 'campur']);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);
        $ids = collect($result['recommendations'])->pluck('kos.id')->all();

        $this->assertEqualsCanonicalizing([$fit->id, $edgeMin->id, $edgeMax->id], $ids);
        $this->assertSame(3, $result['candidate_count']);
        foreach ($result['recommendations'] as $rec) {
            $this->assertSame('Karawaci', $rec['kos']->location);
            $this->assertGreaterThanOrEqual(4000000, $rec['kos']->price);
            $this->assertLessThanOrEqual(6000000, $rec['kos']->price);
        }
    }

    public function test_bsd_preference_matches_bsd_city_kos(): void
    {
        $user = $this->makeUserWithProfile(['preferred_location' => 'BSD']);
        $bsd = Kos::factory()->create(['location' => 'BSD City', 'price' => 2000000, 'gender_type' => 'campur']);
        Kos::factory()->create(['location' => 'Serpong', 'price' => 2000000, 'gender_type' => 'campur']);

        $this->assertSame([$bsd->id], $this->recommendedIds($user));
    }

    public function test_highly_rated_kos_outside_preferences_stays_out_in_warm_start(): void
    {
        $user = $this->makeUserWithProfile(['preferred_location' => 'Karawaci', 'budget_min' => 1000000, 'budget_max' => 3000000]);
        $fit = $this->matchingKos(1)->first();
        $bsd = Kos::factory()->create(['location' => 'BSD City', 'price' => 2000000, 'gender_type' => 'campur']);

        // Pengguna lain yang seleranya mirip memberi rating tinggi pada kos BSD.
        $other = User::factory()->create(['role' => 'user']);
        UserInteraction::create(['user_id' => $user->id, 'kos_id' => $fit->id, 'rating' => 5]);
        UserInteraction::create(['user_id' => $other->id, 'kos_id' => $fit->id, 'rating' => 5]);
        UserInteraction::create(['user_id' => $other->id, 'kos_id' => $bsd->id, 'rating' => 5]);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);

        $this->assertFalse($result['is_cold_start']);
        $this->assertSame([$fit->id], collect($result['recommendations'])->pluck('kos.id')->all());
    }

    public function test_detail_of_kos_outside_preferences_explains_instead_of_matching(): void
    {
        $user = $this->makeUserWithProfile(['preferred_location' => 'Karawaci', 'budget_min' => 4000000, 'budget_max' => 6000000]);
        $bsd = Kos::factory()->create(['location' => 'BSD City', 'price' => 5000000, 'gender_type' => 'campur']);
        $cheap = Kos::factory()->create(['location' => 'Karawaci', 'price' => 3500000, 'gender_type' => 'campur']);

        $service = new RecommendationService();
        $forBsd = $service->getRecommendationForKos($user->id, $bsd);
        $forCheap = $service->getRecommendationForKos($user->id, $cheap);

        $this->assertTrue($forBsd['outside_preference']);
        $this->assertSame(0, $forBsd['match_percentage']);
        $this->assertStringContainsString('Di luar area preferensi', $forBsd['explanation'][0]);
        $this->assertStringContainsString('Di luar budget', $forCheap['explanation'][0]);
    }

    public function test_recommendations_are_sorted_descending_by_hybrid_score(): void
    {
        $user = $this->makeUserWithProfile();
        $this->matchingKos(5);

        $result = (new RecommendationService())->getRecommendations($user->id, 10);
        $scores = collect($result['recommendations'])->pluck('score_hybrid')->all();

        $sorted = $scores;
        rsort($sorted);
        $this->assertEquals($sorted, $scores);
    }
}
