<?php

namespace App\Services\Recommendation;

use App\Models\Facility;
use App\Models\Rule;
use App\Models\Kos;
use App\Models\UserProfile;
use Illuminate\Support\Collection;

/**
 * Modul Content-Based Filtering (skripsi subbab 2.2.1): merepresentasikan
 * profil pengguna dan atribut kos sebagai vektor fitur, lalu mengukur
 * kemiripannya lewat CosineSimilarity.
 */
class ContentBasedFilter
{
    // Diambil dari database (bukan hardcode) agar fasilitas/aturan baru yang
    // ditambahkan admin otomatis ikut memengaruhi skor tanpa perlu ubah kode.
    protected ?array $facilityList = null;
    protected ?array $ruleList = null;

    protected function facilityList(): array
    {
        return $this->facilityList ??= Facility::orderBy('id')->pluck('name')->all();
    }

    protected function ruleList(): array
    {
        return $this->ruleList ??= Rule::orderBy('id')->pluck('name')->all();
    }

    /**
     * Hitung skor Content-Based untuk semua kos berdasarkan profil pengguna.
     */
    public function calculateScores(UserProfile $profile, Collection $koses): array
    {
        $scores = [];
        $userVector = $this->createUserPreferenceVector($profile);

        foreach ($koses as $kos) {
            // Hard Constraint: Gender Match.
            // Pria tidak boleh masuk kos putri, wanita tidak boleh masuk kos putra.
            $kosGender = strtolower($kos->gender_type);
            $userGender = strtolower($profile->gender);

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

    /**
     * Kebalikan dari calculateScores(): untuk satu kos tertentu, hitung skor
     * kecocokan terhadap kumpulan profil penyewa. Dipakai oleh pemilik kos
     * untuk melihat "penyewa mana yang paling cocok dengan kos saya" --
     * memakai vektor & rumus cosine similarity yang persis sama, cuma arah
     * perulangannya dibalik (per profil, bukan per kos).
     *
     * @return array<int, float> keyed by user_id
     */
    public function calculateScoresForKos(Kos $kos, Collection $profiles): array
    {
        $scores = [];
        $kosGender = strtolower($kos->gender_type);

        foreach ($profiles as $profile) {
            $userGender = strtolower($profile->gender);

            if (($kosGender === 'putri' && $userGender === 'pria') ||
                ($kosGender === 'putra' && $userGender === 'wanita')) {
                $scores[$profile->user_id] = 0.0;
                continue;
            }

            $userVector = $this->createUserPreferenceVector($profile);
            $kosVector = $this->createKosAttributeVector($kos, $profile);
            $scores[$profile->user_id] = CosineSimilarity::calculate($userVector, $kosVector);
        }

        return $scores;
    }

    /**
     * Membuat vektor preferensi pengguna (15 dimensi: tipe kos, budget,
     * jarak, fasilitas, aturan).
     */
    protected function createUserPreferenceVector(UserProfile $profile): array
    {
        $vector = [];

        // 1-3. Tipe Kos (putra, putri, campur)
        // Pengguna pria tertarik pada kos putra & campur. Wanita tertarik pada putri & campur.
        $userGender = strtolower($profile->gender);
        if ($userGender === 'pria') {
            $vector[] = 1.0; // putra
            $vector[] = 0.0; // putri
            $vector[] = 1.0; // campur
        } else {
            $vector[] = 0.0; // putra
            $vector[] = 1.0; // putri
            $vector[] = 1.0; // campur
        }

        // 4. Harga/Budget (pengguna menginginkan harga yang sesuai dengan budgetnya = 1.0)
        $vector[] = 1.0;

        // 5. Jarak (pengguna menginginkan jarak sedekat mungkin = 1.0)
        $vector[] = 1.0;

        // 6-11. Fasilitas
        $prefFacilities = $profile->preferred_facilities ?? [];
        foreach ($this->facilityList() as $facName) {
            $vector[] = in_array($facName, $prefFacilities) ? 1.0 : 0.0;
        }

        // 12-15. Aturan
        $prefRules = $profile->preferred_rules ?? [];
        foreach ($this->ruleList() as $ruleName) {
            $vector[] = in_array($ruleName, $prefRules) ? 1.0 : 0.0;
        }

        return $vector;
    }

    /**
     * Membuat vektor atribut kos untuk dibandingkan dengan preferensi user.
     */
    protected function createKosAttributeVector(Kos $kos, UserProfile $profile): array
    {
        $vector = [];

        // 1-3. Tipe Kos (putra, putri, campur)
        $kosGender = strtolower($kos->gender_type);
        $vector[] = ($kosGender === 'putra') ? 1.0 : 0.0;
        $vector[] = ($kosGender === 'putri') ? 1.0 : 0.0;
        $vector[] = ($kosGender === 'campur') ? 1.0 : 0.0;

        // 4. Kesesuaian Budget.
        // Jika harga masuk rentang budget, skor 1.0.
        // Jika di luar rentang, hitung deviasinya secara linear.
        $price = $kos->price;
        $min = $profile->budget_min ?? 1000000;
        $max = $profile->budget_max ?? 3000000;

        if ($price >= $min && $price <= $max) {
            $vector[] = 1.0;
        } elseif ($price < $min) {
            $vector[] = $price / $min;
        } else {
            $vector[] = max(0.0, 1.0 - (($price - $max) / $max));
        }

        // 5. Jarak ke Kampus (Jarak lebih dekat = nilai lebih besar).
        // Menggunakan formula: 1 / (1 + jarak_km)
        $distance = $kos->distance_to_campus;
        $vector[] = 1.0 / (1.0 + $distance);

        // 6-11. Fasilitas
        $kosFacilities = $kos->facilities->pluck('name')->toArray();
        foreach ($this->facilityList() as $facName) {
            $vector[] = in_array($facName, $kosFacilities) ? 1.0 : 0.0;
        }

        // 12-15. Aturan
        $kosRules = $kos->rules->pluck('name')->toArray();
        foreach ($this->ruleList() as $ruleName) {
            $vector[] = in_array($ruleName, $kosRules) ? 1.0 : 0.0;
        }

        return $vector;
    }
}
