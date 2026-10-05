<?php

namespace App\Services\Evaluation;

use App\Models\Source\SourceKos;
use App\Models\Source\SourceUser;
use App\Models\Source\SourceUserInteraction;
use App\Services\Recommendation\CollaborativeFilter;
use App\Services\Recommendation\ContentBasedFilter;
use Illuminate\Support\Collection;

/**
 * Mengevaluasi kualitas rekomendasi hybrid (dan pembandingnya: CB murni,
 * CF murni, popularitas non-personal) memakai data interaksi ASLI dari
 * database "koskita" (via koneksi "source", baca-saja).
 *
 * Protokol: holdout per pengguna (skripsi Bab III -- metode evaluasi),
 * dalam dua skenario:
 *
 * - Warm-start: untuk tiap pengguna dengan >=2 rating "relevan" (rating
 *   >= 4), sebagian rating relevan terbarunya (~20%, minimal 1)
 *   DISEMBUNYIKAN dari data latih sebagai ground truth, sisanya tetap
 *   dipakai membangun profil CF.
 * - Cold-start: untuk tiap pengguna dengan >=1 rating relevan, SELURUH
 *   ratingnya disembunyikan, sehingga ia dievaluasi sebagai pengguna baru
 *   yang baru mengisi profil. Semua kos yang ia beri rating relevan menjadi
 *   ground truth. Ini menguji mekanisme switching (alpha = 1) yang sama
 *   dengan production: apakah profil saja sudah cukup untuk menebak kos
 *   yang nantinya ia sukai.
 *
 * Sistem lalu diminta meranking seluruh kos yang belum diketahui user itu;
 * kalau ground truth yang disembunyikan muncul di posisi atas (Top-K),
 * berarti sistem "menebak dengan benar" apa yang sebenarnya disukai user.
 *
 * Ini leave-one-out/holdout evaluation, pendekatan standar riset sistem
 * rekomendasi kalau tidak ada dataset rating eksplisit terpisah untuk
 * train/test (relevan untuk kondisi 150 responden yang interaksinya
 * terbatas).
 */
class EvaluationService
{
    public const RELEVANCE_THRESHOLD = 4;
    public const HOLDOUT_RATIO = 0.2;

    public const SCENARIO_WARM = 'warm';
    public const SCENARIO_COLD = 'cold';

    public function __construct(
        protected ContentBasedFilter $cb = new ContentBasedFilter(),
        protected CollaborativeFilter $cf = new CollaborativeFilter(),
    ) {
    }

    /**
     * @param string $strategy 'hybrid' | 'cb_only' | 'cf_only' | 'popularity'
     * @param array<int> $kValues
     * @param string $scenario self::SCENARIO_WARM | self::SCENARIO_COLD
     */
    public function evaluate(string $strategy, float $alpha = 0.6, array $kValues = [5, 10], string $scenario = self::SCENARIO_WARM): array
    {
        $koses = SourceKos::with('facilities', 'rules')->get();
        $allInteractions = SourceUserInteraction::whereNotNull('rating')->get();
        $byUser = $allInteractions->groupBy('user_id');
        $users = SourceUser::where('role', 'user')->with('profile')->get()->keyBy('id');

        $perK = [];
        foreach ($kValues as $k) {
            $perK[$k] = ['precision' => [], 'recall' => [], 'ndcg' => [], 'ap' => []];
        }
        $usersEvaluated = 0;

        foreach ($byUser as $userId => $interactions) {
            $user = $users->get($userId);
            if (!$user || !$user->profile) {
                continue;
            }

            $split = $scenario === self::SCENARIO_COLD
                ? $this->coldStartSplit((int) $userId, $interactions, $allInteractions)
                : $this->warmStartSplit((int) $userId, $interactions, $allInteractions);
            if ($split === null) {
                continue;
            }
            [$testKosIds, $trainInteractions] = $split;

            $knownKosIds = $trainInteractions->where('user_id', $userId)->pluck('kos_id')->all();
            $isColdStart = empty($knownKosIds);

            $candidateKosIds = $koses->pluck('id')->reject(fn ($id) => in_array($id, $knownKosIds))->values();

            // Popularitas dihitung dari data latih pengguna ini, bukan dari
            // seluruh data: rating yang sedang disembunyikan sebagai ground
            // truth tidak boleh ikut mendongkrak skor kos ujinya sendiri.
            $popularity = $strategy === 'popularity' ? $this->popularityScores($trainInteractions) : [];

            $rankedIds = $this->rank($strategy, $alpha, $isColdStart, $user, $koses, (int) $userId, $trainInteractions, $popularity, $candidateKosIds->all());

            foreach ($kValues as $k) {
                $perK[$k]['precision'][] = RecommendationMetrics::precisionAtK($rankedIds, $testKosIds, $k);
                $perK[$k]['recall'][] = RecommendationMetrics::recallAtK($rankedIds, $testKosIds, $k);
                $perK[$k]['ndcg'][] = RecommendationMetrics::ndcgAtK($rankedIds, $testKosIds, $k);
                $perK[$k]['ap'][] = RecommendationMetrics::averagePrecisionAtK($rankedIds, $testKosIds, $k);
            }
            $usersEvaluated++;
        }

        $metricsByK = [];
        foreach ($kValues as $k) {
            $metricsByK[$k] = [
                'precision' => $this->mean($perK[$k]['precision']),
                'recall' => $this->mean($perK[$k]['recall']),
                'ndcg' => $this->mean($perK[$k]['ndcg']),
                'map' => $this->mean($perK[$k]['ap']),
            ];
        }

        return [
            'strategy' => $strategy,
            'alpha' => $alpha,
            'scenario' => $scenario,
            'users_evaluated' => $usersEvaluated,
            'metrics_by_k' => $metricsByK,
        ];
    }

    /**
     * Warm-start: sembunyikan ~20% rating relevan terbaru (minimal 1) sebagai
     * ground truth, sisanya tetap jadi sinyal latih. Butuh minimal 2 rating
     * relevan supaya setelah disembunyikan masih ada yang tersisa.
     *
     * @return array{0: array<int>, 1: Collection}|null [id kos ground truth, interaksi latih]
     */
    protected function warmStartSplit(int $userId, Collection $interactions, Collection $allInteractions): ?array
    {
        $relevant = $interactions->where('rating', '>=', self::RELEVANCE_THRESHOLD)->sortByDesc('created_at')->values();
        if ($relevant->count() < 2) {
            return null;
        }

        $holdoutCount = max(1, (int) round($relevant->count() * self::HOLDOUT_RATIO));
        $testKosIds = $relevant->take($holdoutCount)->pluck('kos_id')->all();

        $trainInteractions = $allInteractions->reject(
            fn ($inter) => (int) $inter->user_id === $userId && in_array($inter->kos_id, $testKosIds)
        );

        return [$testKosIds, $trainInteractions];
    }

    /**
     * Cold-start: sembunyikan SELURUH rating pengguna (relevan maupun tidak),
     * sehingga di mata sistem ia belum pernah berinteraksi sama sekali dan
     * hanya profilnya yang tersedia. Semua kos yang ia beri rating relevan
     * menjadi ground truth, jadi cukup 1 rating relevan untuk bisa dievaluasi.
     *
     * @return array{0: array<int>, 1: Collection}|null [id kos ground truth, interaksi latih]
     */
    protected function coldStartSplit(int $userId, Collection $interactions, Collection $allInteractions): ?array
    {
        $testKosIds = $interactions->where('rating', '>=', self::RELEVANCE_THRESHOLD)->pluck('kos_id')->unique()->values()->all();
        if (empty($testKosIds)) {
            return null;
        }

        $trainInteractions = $allInteractions->reject(fn ($inter) => (int) $inter->user_id === $userId);

        return [$testKosIds, $trainInteractions];
    }

    /**
     * @return array<int> id kos terurut dari yang paling direkomendasikan.
     */
    protected function rank(
        string $strategy,
        float $alpha,
        bool $isColdStart,
        SourceUser $user,
        Collection $koses,
        int $userId,
        Collection $trainInteractions,
        array $popularity,
        array $candidateKosIds
    ): array {
        if ($strategy === 'popularity') {
            $filtered = array_intersect_key($popularity, array_flip($candidateKosIds));
            arsort($filtered);
            return array_keys($filtered);
        }

        $scoreCB = $this->cb->calculateScores($user->profile, $koses);
        $scoreCF = $strategy === 'cb_only'
            ? []
            : $this->cf->calculateScores($userId, $koses, $trainInteractions);

        $ranked = [];
        foreach ($candidateKosIds as $kosId) {
            $cbScore = $scoreCB[$kosId] ?? 0.0;
            $cfScore = $scoreCF[$kosId] ?? 0.0;

            $ranked[$kosId] = match ($strategy) {
                'cb_only' => $cbScore,
                'cf_only' => $cfScore,
                default => ($isColdStart ? 1.0 : $alpha) * $cbScore + (1.0 - ($isColdStart ? 1.0 : $alpha)) * $cfScore,
            };
        }

        if ($strategy === 'cf_only') {
            // Skor CF nol berarti "tidak ada tetangga yang menilai kos ini",
            // bukan "peringkat terakhir". Sama seperti baseline popularitas
            // yang hanya meranking kos yang punya skor, kos tanpa prediksi
            // tidak direkomendasikan. Tanpa penyaringan ini kos ber-skor nol
            // terurut menurut id database dan bisa "kebetulan" mengenai
            // ground truth -- terutama saat cold-start, ketika SELURUH skor
            // CF bernilai nol.
            $ranked = array_filter($ranked, fn ($score) => $score > 0);
        }

        arsort($ranked);
        return array_keys($ranked);
    }

    /**
     * Baseline non-personalized: rata-rata rating per kos dari interaksi
     * yang diberikan (pemanggil mengoper data latih, bukan seluruh data).
     */
    protected function popularityScores(Collection $interactions): array
    {
        $scores = [];
        foreach ($interactions->groupBy('kos_id') as $kosId => $group) {
            $scores[$kosId] = (float) $group->avg('rating');
        }
        return $scores;
    }

    protected function mean(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0.0;
    }

    /**
     * Eksperimen mencari alpha optimal (skripsi: "nilai alpha dapat
     * ditentukan melalui eksperimen") -- menjalankan evaluasi hybrid untuk
     * beberapa nilai alpha sekaligus supaya bisa dibandingkan di dashboard.
     * Hanya bermakna pada warm-start: saat cold-start alpha selalu 1.
     */
    public function compareAlphas(array $alphas = [0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8], array $kValues = [5, 10]): array
    {
        return array_map(fn ($alpha) => $this->evaluate('hybrid', $alpha, $kValues), $alphas);
    }

    /**
     * Hybrid vs baseline (skripsi: pembuktian ilmiah kontribusi model
     * hybrid dibanding pendekatan popularitas/CB murni/CF murni), untuk
     * skenario warm-start maupun cold-start.
     */
    public function compareBaselines(float $chosenAlpha = 0.6, array $kValues = [5, 10], string $scenario = self::SCENARIO_WARM): array
    {
        return [
            $this->evaluate('hybrid', $chosenAlpha, $kValues, $scenario),
            $this->evaluate('cb_only', 1.0, $kValues, $scenario),
            $this->evaluate('cf_only', 0.0, $kValues, $scenario),
            $this->evaluate('popularity', 0.0, $kValues, $scenario),
        ];
    }
}
