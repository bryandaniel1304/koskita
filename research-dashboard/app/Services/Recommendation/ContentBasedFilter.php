<?php

namespace App\Services\Recommendation;

use App\Models\Source\SourceFacility;
use App\Models\Source\SourceRule;
use App\Models\Source\SourceKos;
use App\Models\Source\SourceUserProfile;
use Illuminate\Support\Collection;

/**
 * Port dari backend/app/Services/Recommendation/ContentBasedFilter.php --
 * formula & urutan dimensi vektor SENGAJA dibuat identik dengan production
 * (skripsi subbab 2.2.1), cuma sumber datanya lewat Source*Model (koneksi
 * "source", baca-saja) supaya dashboard ini tidak butuh depend ke folder
 * backend/ sama sekali.
 */
class ContentBasedFilter
{
    protected ?array $facilityList = null;
    protected ?array $ruleList = null;

    protected function facilityList(): array
    {
        return $this->facilityList ??= SourceFacility::orderBy('id')->pluck('name')->all();
    }

    protected function ruleList(): array
    {
        return $this->ruleList ??= SourceRule::orderBy('id')->pluck('name')->all();
    }

    /**
     * @param Collection<int, SourceKos> $koses
     * @return array<int, float>
     */
    public function calculateScores(SourceUserProfile $profile, Collection $koses): array
    {
        $scores = [];
        $userVector = $this->createUserPreferenceVector($profile);

        foreach ($koses as $kos) {
            $kosGender = strtolower((string) $kos->gender_type);
            $userGender = strtolower((string) $profile->gender);

            if (($kosGender === 'putri' && $userGender === 'pria') ||
                ($kosGender === 'putra' && $userGender === 'wanita')) {
                $scores[$kos->id] = 0.0;
                continue;
            }

            $kosVector = $this->createKosAttributeVector($kos, $profile);
            $scores[$kos->id] = CosineSimilarity::calculate($userVector, $kosVector);
        }

        return $scores;
    }

    protected function createUserPreferenceVector(SourceUserProfile $profile): array
    {
        $vector = [];

        $userGender = strtolower((string) $profile->gender);
        if ($userGender === 'pria') {
            $vector[] = 1.0;
            $vector[] = 0.0;
            $vector[] = 1.0;
        } else {
            $vector[] = 0.0;
            $vector[] = 1.0;
            $vector[] = 1.0;
        }

        $vector[] = 1.0; // Budget: user selalu "menginginkan" harga sesuai budgetnya
        $vector[] = 1.0; // Jarak: user selalu menginginkan sedekat mungkin

        $prefFacilities = $profile->preferred_facilities ?? [];
        foreach ($this->facilityList() as $facName) {
            $vector[] = in_array($facName, $prefFacilities) ? 1.0 : 0.0;
        }

        $prefRules = $profile->preferred_rules ?? [];
        foreach ($this->ruleList() as $ruleName) {
            $vector[] = in_array($ruleName, $prefRules) ? 1.0 : 0.0;
        }

        return $vector;
    }

    protected function createKosAttributeVector(SourceKos $kos, SourceUserProfile $profile): array
    {
        $vector = [];

        $kosGender = strtolower((string) $kos->gender_type);
        $vector[] = ($kosGender === 'putra') ? 1.0 : 0.0;
        $vector[] = ($kosGender === 'putri') ? 1.0 : 0.0;
        $vector[] = ($kosGender === 'campur') ? 1.0 : 0.0;

        $price = $kos->price;
        $min = $profile->budget_min ?? 1000000;
        $max = $profile->budget_max ?? 3000000;

        if ($price >= $min && $price <= $max) {
            $vector[] = 1.0;
        } elseif ($price < $min) {
            $vector[] = $min > 0 ? $price / $min : 0.0;
        } else {
            $vector[] = max(0.0, 1.0 - (($price - $max) / max($max, 1)));
        }

        $distance = $kos->distance_to_campus;
        $vector[] = 1.0 / (1.0 + $distance);

        $kosFacilities = $kos->facilities->pluck('name')->toArray();
        foreach ($this->facilityList() as $facName) {
            $vector[] = in_array($facName, $kosFacilities) ? 1.0 : 0.0;
        }

        $kosRules = $kos->rules->pluck('name')->toArray();
        foreach ($this->ruleList() as $ruleName) {
            $vector[] = in_array($ruleName, $kosRules) ? 1.0 : 0.0;
        }

        return $vector;
    }
}
