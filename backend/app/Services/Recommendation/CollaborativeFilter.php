<?php

namespace App\Services\Recommendation;

use App\Models\UserInteraction;
use Illuminate\Support\Collection;

/**
 * Modul Collaborative Filtering berbasis memori (skripsi subbab 2.2.2):
 * menghitung kemiripan antarpengguna langsung dari matriks rating
 * menggunakan CosineSimilarity, tanpa proses pelatihan model.
 */
class CollaborativeFilter
{
    /**
     * Hitung skor User-Based Collaborative Filtering untuk semua kos.
     */
    public function calculateScores(int $targetUserId, Collection $koses): array
    {
        // Ambil semua interaksi rating di database
        $interactions = UserInteraction::whereNotNull('rating')->get();

        // 1. Buat Rating Matrix [user_id][kos_id] => rating (1-5)
        $matrix = [];
        foreach ($interactions as $inter) {
            $matrix[$inter->user_id][$inter->kos_id] = $inter->rating;
        }

        $scores = [];

        // Jika tidak ada data rating di sistem sama sekali, return skor 0 untuk semua kos
        if (empty($matrix) || !isset($matrix[$targetUserId])) {
            foreach ($koses as $kos) {
                $scores[$kos->id] = 0.0;
            }
            return $scores;
        }

        $targetRatings = $matrix[$targetUserId];

        // 2. Hitung Kemiripan Cosine antara Target User dan semua user lainnya
        $userSimilarities = [];
        foreach ($matrix as $otherUserId => $otherRatings) {
            if ($otherUserId === $targetUserId) {
                continue;
            }

            // Samakan dimensi vektor rating untuk menghitung cosine similarity
            $vecA = [];
            $vecB = [];

            // Satukan daftar kos yang pernah dirating oleh A atau B
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

        // 3. Prediksi Rating untuk setiap kos yang belum dirating oleh target user
        foreach ($koses as $kos) {
            // Jika user sudah merating kos ini, kita bisa memberikan skor berdasarkan rating aktualnya
            if (isset($targetRatings[$kos->id])) {
                $scores[$kos->id] = $targetRatings[$kos->id] / 5.0; // Normalisasi ke [0,1]
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
                $scores[$kos->id] = $predictedRating / 5.0; // Normalisasi ke [0,1]
            } else {
                $scores[$kos->id] = 0.0;
            }
        }

        return $scores;
    }
}
