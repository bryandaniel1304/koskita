<?php

namespace App\Services\Recommendation;

use App\Models\Source\SourceUserInteraction;
use Illuminate\Support\Collection;

/**
 * Port dari backend/app/Services/Recommendation/CollaborativeFilter.php
 * (user-based, memory-based CF -- skripsi subbab 2.2.2).
 *
 * Beda dengan versi production: di sini interaksi TIDAK selalu diquery
 * fresh dari DB begitu saja -- EvaluationService yang memanggil method ini
 * boleh mengoper koleksi interaksi yang sudah disaring (dikecualikan item
 * yang sengaja "disembunyikan" sebagai data uji lewat protokol
 * leave-one-out), supaya tidak ada kebocoran data uji ke proses ranking.
 */
class CollaborativeFilter
{
    /**
     * @param Collection<int, SourceUserInteraction>|null $interactions Jika null, ambil semua dari DB (dipakai live, bukan evaluasi).
     */
    public function calculateScores(int $targetUserId, Collection $koses, ?Collection $interactions = null): array
    {
        $interactions ??= SourceUserInteraction::whereNotNull('rating')->get();

        $matrix = [];
        foreach ($interactions as $inter) {
            $matrix[$inter->user_id][$inter->kos_id] = $inter->rating;
        }

        $scores = [];

        if (empty($matrix) || !isset($matrix[$targetUserId])) {
            foreach ($koses as $kos) {
                $scores[$kos->id] = 0.0;
            }
            return $scores;
        }

        $targetRatings = $matrix[$targetUserId];

        $userSimilarities = [];
        foreach ($matrix as $otherUserId => $otherRatings) {
            if ($otherUserId === $targetUserId) {
                continue;
            }

            $vecA = [];
            $vecB = [];
            $allKosIds = array_unique(array_merge(array_keys($targetRatings), array_keys($otherRatings)));
            foreach ($allKosIds as $kosId) {
                $vecA[] = $targetRatings[$kosId] ?? 0;
                $vecB[] = $otherRatings[$kosId] ?? 0;
            }

            $similarity = CosineSimilarity::calculate($vecA, $vecB);
            if ($similarity > 0) {
                $userSimilarities[$otherUserId] = $similarity;
            }
        }

        foreach ($koses as $kos) {
            if (isset($targetRatings[$kos->id])) {
                $scores[$kos->id] = $targetRatings[$kos->id] / 5.0;
                continue;
            }

            $weightedSum = 0.0;
            $similaritySum = 0.0;

            foreach ($userSimilarities as $otherUserId => $similarity) {
                if (isset($matrix[$otherUserId][$kos->id])) {
                    $otherRating = $matrix[$otherUserId][$kos->id];
                    $weightedSum += $similarity * $otherRating;
                    $similaritySum += $similarity;
                }
            }

            if ($similaritySum > 0) {
                $predictedRating = $weightedSum / $similaritySum;
                $scores[$kos->id] = $predictedRating / 5.0;
            } else {
                $scores[$kos->id] = 0.0;
            }
        }

        return $scores;
    }
}
