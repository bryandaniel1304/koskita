<?php

namespace App\Services\Recommendation;

/**
 * Port dari backend/app/Services/Recommendation/SwitchingWeightedCombiner.php
 * (skripsi subbab 2.2.3 & 2.9.1). Alpha sengaja tetap bisa diganti-ganti
 * lewat constructor -- dashboard ini justru dipakai untuk BEREKSPERIMEN
 * mencari alpha optimal (lihat EvaluationService::compareAlphas()), yang di
 * production nilainya sudah ditetapkan 0.6 berdasarkan hasil eksperimen ini.
 */
class SwitchingWeightedCombiner
{
    public function __construct(protected float $alpha = 0.6)
    {
    }

    public function baseAlpha(): float
    {
        return $this->alpha;
    }

    public function effectiveAlpha(bool $isColdStart): float
    {
        return $isColdStart ? 1.0 : $this->alpha;
    }

    public function combine(float $scoreCB, float $scoreCF, bool $isColdStart): float
    {
        $alpha = $this->effectiveAlpha($isColdStart);

        return ($alpha * $scoreCB) + ((1.0 - $alpha) * $scoreCF);
    }
}
