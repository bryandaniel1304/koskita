<?php

namespace App\Services\Recommendation;

/**
 * Port 1:1 dari backend/app/Services/Recommendation/CosineSimilarity.php --
 * sengaja disalin persis (bukan di-refactor/diringkas) supaya metrik yang
 * dihasilkan dashboard ini benar-benar mengevaluasi algoritma yang SAMA
 * dengan yang berjalan di production, bukan pendekatan lain.
 */
class CosineSimilarity
{
    public static function calculate(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = count($vecA);

        for ($i = 0; $i < $count; $i++) {
            $valA = $vecA[$i] ?? 0.0;
            $valB = $vecB[$i] ?? 0.0;

            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
