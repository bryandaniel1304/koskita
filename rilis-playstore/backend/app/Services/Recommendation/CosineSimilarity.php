<?php

namespace App\Services\Recommendation;

/**
 * Pengukuran kemiripan antar-vektor yang dipakai oleh ContentBasedFilter
 * (kemiripan profil vs atribut kos) dan CollaborativeFilter (kemiripan
 * antarpengguna berdasarkan matriks rating).
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
