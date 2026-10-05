<?php

namespace Tests\Feature;

use App\Models\EvaluationRun;
use App\Services\Evaluation\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $kos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $ac = $this->facility('AC');
        $wifi = $this->facility('WiFi');

        // Untuk penyewa wanita yang mencari AC + WiFi, Content-Based
        // meranking: Putri Lengkap > Putri AC = Campur AC > Campur Polos,
        // sedangkan kos putra bernilai nol karena batasan jenis kelamin.
        $this->kos = [
            'putri_lengkap' => $this->kos('Putri Lengkap', 'putri', [$ac, $wifi]),
            'putri_ac' => $this->kos('Putri AC', 'putri', [$ac]),
            'putra_lengkap' => $this->kos('Putra Lengkap', 'putra', [$ac, $wifi]),
            'putra_polos' => $this->kos('Putra Polos', 'putra', []),
            'campur_polos' => $this->kos('Campur Polos', 'campur', []),
            'campur_ac' => $this->kos('Campur AC', 'campur', [$ac]),
        ];
    }

    public function test_cold_start_hides_every_rating_and_falls_back_to_content_based(): void
    {
        $tenant = $this->tenant('wanita', ['AC', 'WiFi']);
        $this->rate($tenant, 'putri_lengkap', 5, '2026-09-01');
        $this->rate($tenant, 'putri_ac', 4, '2026-09-02');
        $this->rate($tenant, 'campur_polos', 2, '2026-08-30');
        $this->otherTenantWithLowRatings();

        $service = new EvaluationService();
        $hybrid = $service->evaluate('hybrid', 0.6, [5], EvaluationService::SCENARIO_COLD);
        $cbOnly = $service->evaluate('cb_only', 1.0, [5], EvaluationService::SCENARIO_COLD);

        // Hanya penyewa yang punya rating relevan yang dievaluasi.
        $this->assertSame(1, $hybrid['users_evaluated']);
        $this->assertSame(EvaluationService::SCENARIO_COLD, $hybrid['scenario']);

        // Kedua kos yang ia sukai (rating 5 dan 4) ditebak dari profilnya saja.
        $this->assertEqualsWithDelta(1.0, $hybrid['metrics_by_k'][5]['recall'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $hybrid['metrics_by_k'][5]['precision'], 1e-9);

        // Switching menetapkan alpha = 1, jadi hybrid identik dengan CB murni.
        $this->assertEquals($cbOnly['metrics_by_k'], $hybrid['metrics_by_k']);
    }

    public function test_cold_start_collaborative_filtering_recommends_nothing(): void
    {
        $tenant = $this->tenant('wanita', ['AC', 'WiFi']);
        $this->rate($tenant, 'putri_lengkap', 5, '2026-09-01');
        $this->rate($tenant, 'putri_ac', 4, '2026-09-02');
        $this->otherTenantWithLowRatings();

        $result = (new EvaluationService())->evaluate('cf_only', 0.0, [5], EvaluationService::SCENARIO_COLD);

        // Tanpa rating milik sendiri seluruh skor CF nol. Kos ber-skor nol
        // tidak boleh "kebetulan" mengenai ground truth hanya karena id-nya
        // kecil (kedua kos yang disukai di sini justru id 1 dan 2).
        $this->assertSame(1, $result['users_evaluated']);
        foreach (['precision', 'recall', 'ndcg', 'map'] as $metric) {
            $this->assertSame(0.0, $result['metrics_by_k'][5][$metric], $metric);
        }
    }

    public function test_warm_start_still_holds_out_only_the_latest_relevant_rating(): void
    {
        $tenant = $this->tenant('wanita', ['AC', 'WiFi']);
        $this->rate($tenant, 'campur_polos', 2, '2026-08-30');
        $this->rate($tenant, 'putri_lengkap', 5, '2026-09-01');
        $this->rate($tenant, 'putri_ac', 4, '2026-09-02');
        $this->otherTenantWithLowRatings();

        $result = (new EvaluationService())->evaluate('hybrid', 0.6, [5]);

        // Kos yang dinilai paling akhir (Putri AC) disembunyikan; sisanya
        // tetap diketahui, sehingga ia masih berstatus warm-start dan Putri
        // AC muncul di peringkat 2 di bawah Campur AC yang didorong CF.
        $this->assertSame(EvaluationService::SCENARIO_WARM, $result['scenario']);
        $this->assertSame(1, $result['users_evaluated']);
        $this->assertEqualsWithDelta(1.0, $result['metrics_by_k'][5]['recall'], 1e-9);
        $this->assertEqualsWithDelta(1 / log(3, 2), $result['metrics_by_k'][5]['ndcg'], 1e-9);
    }

    public function test_popularity_baseline_does_not_see_the_hidden_ratings(): void
    {
        $tenant = $this->tenant('wanita', ['AC', 'WiFi']);
        $this->rate($tenant, 'campur_polos', 2, '2026-08-30');
        $this->rate($tenant, 'putri_lengkap', 5, '2026-09-01');
        $this->rate($tenant, 'putri_ac', 5, '2026-09-02');
        $this->otherTenantWithLowRatings();

        $warm = (new EvaluationService())->evaluate('popularity', 0.0, [5]);
        $cold = (new EvaluationService())->evaluate('popularity', 0.0, [5], EvaluationService::SCENARIO_COLD);

        // Putri AC hanya pernah dinilai oleh penyewa ini sendiri, dan nilai
        // itulah yang sedang disembunyikan. Kalau popularitas dihitung dari
        // seluruh data, rata-ratanya 5 dan ia langsung "tertebak" di urutan
        // pertama -- kebocoran data uji ke baseline.
        $this->assertSame(0.0, $warm['metrics_by_k'][5]['recall']);
        $this->assertSame(0.0, $cold['metrics_by_k'][5]['recall']);
    }

    public function test_cold_start_batch_is_stored_and_shown_on_the_evaluation_page(): void
    {
        $tenant = $this->tenant('wanita', ['AC', 'WiFi']);
        $this->rate($tenant, 'putri_lengkap', 5, '2026-09-01');
        $this->rate($tenant, 'putri_ac', 4, '2026-09-02');
        $this->otherTenantWithLowRatings();

        $this->post(route('evaluation.run-cold-start'))->assertRedirect(route('evaluation.index'));

        $runs = EvaluationRun::all();
        $this->assertCount(8, $runs); // 4 strategi x K = 5 dan 10
        $this->assertTrue($runs->every(fn ($run) => str_starts_with($run->batch_label, 'coldstart-')));
        $this->assertEqualsCanonicalizing(['hybrid', 'cb_only', 'cf_only', 'popularity'], $runs->pluck('strategy')->unique()->values()->all());

        $this->get(route('evaluation.index'))
            ->assertOk()
            ->assertSee('Kesimpulan: Hybrid vs Baseline (Cold-Start)');
    }

    private function facility(string $name): int
    {
        return DB::connection('source')->table('facilities')->insertGetId(['name' => $name]);
    }

    private function kos(string $name, string $gender, array $facilityIds): int
    {
        $id = DB::connection('source')->table('koses')->insertGetId([
            'name' => $name,
            'price' => 1000000,
            'gender_type' => $gender,
            'distance_to_campus' => 1,
        ]);
        foreach ($facilityIds as $facilityId) {
            DB::connection('source')->table('kos_facility')->insert(['kos_id' => $id, 'facility_id' => $facilityId]);
        }
        return $id;
    }

    private function tenant(string $gender, array $facilities): int
    {
        $id = DB::connection('source')->table('users')->insertGetId(['name' => "Penyewa $gender", 'role' => 'user']);
        DB::connection('source')->table('user_profiles')->insert([
            'user_id' => $id,
            'gender' => $gender,
            'occupation' => 'mahasiswa',
            'budget_min' => 500000,
            'budget_max' => 1500000,
            'preferred_facilities' => json_encode($facilities),
            'preferred_rules' => json_encode([]),
        ]);
        return $id;
    }

    private function rate(int $userId, string $kos, int $rating, string $date): void
    {
        DB::connection('source')->table('user_interactions')->insert([
            'user_id' => $userId,
            'kos_id' => $this->kos[$kos],
            'rating' => $rating,
            'created_at' => "$date 10:00:00",
            'updated_at' => "$date 10:00:00",
        ]);
    }

    /** Penyewa lain yang hanya memberi rating rendah: ikut membentuk CF dan popularitas, tapi tidak pernah dievaluasi. */
    private function otherTenantWithLowRatings(): void
    {
        $other = $this->tenant('pria', ['AC']);
        $this->rate($other, 'campur_polos', 3, '2026-08-01');
        $this->rate($other, 'campur_ac', 3, '2026-08-02');
    }
}
