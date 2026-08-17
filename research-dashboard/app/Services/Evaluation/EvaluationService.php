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
 * Protokol: holdout per pengguna (skripsi Bab III -- metode evaluasi).
 * Untuk tiap pengguna dengan >=2 rating "relevan" (rating >= 4), sebagian
 * rating relevan terbarunya (~20%, minimal 1) DISEMBUNYIKAN dari data
 * latih sebagai ground truth, sisanya tetap dipakai membangun profil CF.
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

    public function __construct(
        protected ContentBasedFilter $cb = new ContentBasedFilter(),
        protected CollaborativeFilter $cf = new CollaborativeFilter(),
    ) {
    }

    /**
     * @param string $strategy 'hybrid' | 'cb_only' | 'cf_only' | 'popularity'
     * @param array<int> $kValues
     */
    public function evaluate(string $strategy, float $alpha = 0.6, array $kValues = [5, 10]): array
    {
        $koses = SourceKos::with('facilities', 'rules')->get();
        $allInteractions = SourceUserInteraction::whereNotNull('rating')->get();
        $byUser = $allInteractions->groupBy('user_id');
        $users = SourceUser::where('role', 'user')->with('profile')->get()->keyBy('id');

        $popularity = $strategy === 'popularity' ? $this->popularityScores($allInteractions) : [];

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

            $relevant = $interactions->where('rating', '>=', self::RELEVANCE_THRESHOLD)->sortByDesc('created_at')->values();
            if ($relevant->count() < 2) {
                // Butuh minimal 2 rating relevan: >=1 disembunyikan sebagai
                // ground truth, sisanya tetap ada sebagai sinyal training.
                continue;
            }

            $holdoutCount = max(1, (int) round($relevant->count() * self::HOLDOUT_RATIO));
            $testKosIds = $relevant->take($holdoutCount)->pluck('kos_id')->all();

            $trainInteractions = $allInteractions->reject(
                fn ($inter) => $inter->user_id === $userId && in_array($inter->kos_id, $testKosIds)
            );
            $knownKosIds = $trainInteractions->where('user_id', $userId)->pluck('kos_id')->all();
            $isColdStart = empty($knownKosIds);

            $candidateKosIds = $koses->pluck('id')->reject(fn ($id) => in_array($id, $knownKosIds))->values();

            $rankedIds = $this->rank($strategy, $alpha, $isColdStart, $user, $koses, $userId, $trainInteractions, $popularity, $candidateKosIds->all());

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
            'users_evaluated' => $usersEvaluated,
            'metrics_by_k' => $metricsByK,
        ];
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

        arsort($ranked);
        return array_keys($ranked);
    }

    /**
     * Baseline non-personalized: rata-rata rating per kos (dari SELURUH
     * data, bukan cuma training user tertentu -- baseline ini memang tidak
     * personal, jadi wajar dihitung sekali dari data global).
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
     */
    public function compareAlphas(array $alphas = [0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8], array $kValues = [5, 10]): array
    {
        return array_map(fn ($alpha) => $this->evaluate('hybrid', $alpha, $kValues), $alphas);
    }

    /**
     * Hybrid vs baseline (skripsi: pembuktian ilmiah kontribusi model
     * hybrid dibanding pendekatan popularitas/CB murni/CF murni).
     */
    public function compareBaselines(float $chosenAlpha = 0.6, array $kValues = [5, 10]): array
    {
        return [
            $this->evaluate('hybrid', $chosenAlpha, $kValues),
            $this->evaluate('cb_only', 1.0, $kValues),
            $this->evaluate('cf_only', 0.0, $kValues),
            $this->evaluate('popularity', 0.0, $kValues),
        ];
    }
}
