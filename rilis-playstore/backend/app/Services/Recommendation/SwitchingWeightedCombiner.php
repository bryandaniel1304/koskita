<?php

namespace App\Services\Recommendation;

/**
 * Modul penggabung (combiner) yang merealisasikan strategi switching hybrid
 * berkomponen weighted (skripsi subbab 2.2.3 & 2.9.1).
 *
 * - Strategi switching: menentukan komponen mana yang aktif berdasarkan
 *   ketersediaan data. Saat cold-start (belum ada rating), alpha otomatis
 *   di-set 1.0 sehingga skor sepenuhnya berasal dari Content-Based Filtering.
 * - Strategi weighted: pada kondisi warm-start, mengatur proporsi kontribusi
 *   Content-Based Filtering dan Collaborative Filtering lewat parameter alpha.
 *
 * Formula: Skor_hybrid = alpha * Skor_CB + (1 - alpha) * Skor_CF
 */
class SwitchingWeightedCombiner
{
    public function __construct(protected float $alpha = 0.6)
    {
    }

    /**
     * Alpha dasar yang dipakai pada kondisi warm-start (bisa ditentukan
     * lewat eksperimen untuk mencari kombinasi performa terbaik).
     */
    public function baseAlpha(): float
    {
        return $this->alpha;
    }

    /**
     * Strategi switching: pilih alpha efektif berdasarkan ketersediaan data
     * interaksi (cold-start -> 1.0, warm-start -> alpha dasar).
     */
    public function effectiveAlpha(bool $isColdStart): float
    {
        return $isColdStart ? 1.0 : $this->alpha;
    }

    /**
     * Strategi weighted: gabungkan skor CB & CF sesuai alpha efektif.
     * Pada cold-start, skor_CF diabaikan (koefisiennya otomatis 0) karena
     * belum ada data interaksi untuk dihitung Collaborative Filter-nya.
     */
    public function combine(float $scoreCB, float $scoreCF, bool $isColdStart): float
    {
        $alpha = $this->effectiveAlpha($isColdStart);

        return ($alpha * $scoreCB) + ((1.0 - $alpha) * $scoreCF);
    }
}
