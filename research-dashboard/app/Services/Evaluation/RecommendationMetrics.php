<?php

namespace App\Services\Evaluation;

/**
 * Metrik evaluasi Information Retrieval standar untuk sistem rekomendasi
 * (skripsi Bab III -- metode evaluasi): Precision@K, Recall@K, NDCG@K, dan
 * Average Precision@K (dirata-ratakan antar pengguna jadi MAP@K).
 *
 * Semua metrik menerima:
 * - $rankedKosIds: daftar id kos hasil rekomendasi, SUDAH terurut dari yang
 *   paling direkomendasikan (skor hybrid tertinggi lebih dulu).
 * - $relevantKosIds: id kos yang jadi "ground truth" -- kos yang di dunia
 *   nyata memang disukai/dirating tinggi oleh user tapi sengaja
 *   disembunyikan dari data latih (protokol leave-one-out/holdout, lihat
 *   EvaluationService).
 */
class RecommendationMetrics
{
    public static function precisionAtK(array $rankedKosIds, array $relevantKosIds, int $k): float
    {
        if ($k <= 0) {
            return 0.0;
        }
        $topK = array_slice($rankedKosIds, 0, $k);
        $hits = count(array_intersect($topK, $relevantKosIds));

        return $hits / $k;
    }

    public static function recallAtK(array $rankedKosIds, array $relevantKosIds, int $k): float
    {
        if (empty($relevantKosIds)) {
            return 0.0;
        }
        $topK = array_slice($rankedKosIds, 0, $k);
        $hits = count(array_intersect($topK, $relevantKosIds));

        return $hits / count($relevantKosIds);
    }

    /**
     * Normalized Discounted Cumulative Gain -- relevansi biner (1 kalau
     * kos ada di $relevantKosIds, 0 kalau tidak), didiskon posisinya makin
     * ke bawah makin kecil bobotnya (log2), dinormalisasi terhadap urutan
     * ideal (semua item relevan ada di posisi paling atas).
     */
    public static function ndcgAtK(array $rankedKosIds, array $relevantKosIds, int $k): float
    {
        $topK = array_slice($rankedKosIds, 0, $k);

        $dcg = 0.0;
        foreach ($topK as $i => $kosId) {
            if (in_array($kosId, $relevantKosIds)) {
                $dcg += 1 / log($i + 2, 2);
            }
        }

        $idealHits = min(count($relevantKosIds), $k);
        $idcg = 0.0;
        for ($i = 0; $i < $idealHits; $i++) {
            $idcg += 1 / log($i + 2, 2);
        }

        return $idcg > 0 ? $dcg / $idcg : 0.0;
    }

    /**
     * Average Precision@K untuk SATU pengguna -- dirata-ratakan lintas
     * pengguna oleh EvaluationService jadi MAP@K. Formula: rata-rata skor
     * precision di setiap posisi yang "hit", dibagi min(jumlah item
     * relevan, K) -- konvensi umum dipakai (mis. kompetisi Kaggle
     * "MAP@K").
     */
    public static function averagePrecisionAtK(array $rankedKosIds, array $relevantKosIds, int $k): float
    {
        if (empty($relevantKosIds)) {
            return 0.0;
        }

        $topK = array_slice($rankedKosIds, 0, $k);
        $hits = 0;
        $sumPrecisionAtHit = 0.0;

        foreach ($topK as $i => $kosId) {
            if (in_array($kosId, $relevantKosIds)) {
                $hits++;
                $sumPrecisionAtHit += $hits / ($i + 1);
            }
        }

        $denominator = min(count($relevantKosIds), $k);

        return $denominator > 0 ? $sumPrecisionAtHit / $denominator : 0.0;
    }
}
