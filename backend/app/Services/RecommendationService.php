<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Kos;
use App\Services\Recommendation\ContentBasedFilter;
use App\Services\Recommendation\CollaborativeFilter;
use App\Services\Recommendation\SwitchingWeightedCombiner;

/**
 * Orkestrator RecommendationService (skripsi subbab 2.9.9): menyatukan tiga
 * modul lapisan logika -- ContentBasedFilter, CollaborativeFilter, dan
 * SwitchingWeightedCombiner -- di atas data profil, atribut kos, dan
 * interaksi pengguna (lapisan data) untuk menghasilkan daftar Top-N kos.
 */
class RecommendationService
{
    public function __construct(
        protected ContentBasedFilter $contentBasedFilter = new ContentBasedFilter(),
        protected CollaborativeFilter $collaborativeFilter = new CollaborativeFilter(),
        protected SwitchingWeightedCombiner $combiner = new SwitchingWeightedCombiner()
    ) {
    }

    /**
     * Mendapatkan rekomendasi kos untuk pengguna tertentu
     */
    public function getRecommendations(int $userId, int $limit = 10): array
    {
        $user = User::with('profile', 'interactions')->findOrFail($userId);

        // Cek apakah user memiliki profil. Jika tidak, buat profil kosong agar tidak error.
        $profile = $user->profile;
        if (!$profile) {
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'gender' => 'pria',
                'occupation' => 'mahasiswa',
                'budget_min' => 1000000,
                'budget_max' => 3000000,
                'preferred_facilities' => [],
                'preferred_rules' => [],
                'preferred_location' => 'Karawaci',
            ]);
        }

        // Cek jumlah interaksi rating (Cold-Start vs Warm-Start) --
        // ini masukan utama bagi strategi switching di SwitchingWeightedCombiner.
        $ratingCount = $user->interactions()->whereNotNull('rating')->count();
        $isColdStart = ($ratingCount === 0);

        // Ambil semua kos dengan relasi fasilitas & aturan
        $koses = Kos::with('facilities', 'rules')->get();

        // Modul Content-Based Filtering selalu dihitung (dipakai baik saat
        // cold-start maupun warm-start).
        $cbScores = $this->contentBasedFilter->calculateScores($profile, $koses);

        // Modul Collaborative Filtering hanya perlu dihitung saat warm-start;
        // saat cold-start datanya memang belum ada untuk dihitung.
        $cfScores = $isColdStart ? [] : $this->collaborativeFilter->calculateScores($userId, $koses);

        $recommendations = [];
        foreach ($koses as $kos) {
            $scoreCB = $cbScores[$kos->id] ?? 0.0;
            $scoreCF = $isColdStart ? 0.0 : ($cfScores[$kos->id] ?? 0.0);

            // Modul penggabung: strategi switching-weighted.
            $scoreHybrid = $this->combiner->combine($scoreCB, $scoreCF, $isColdStart);

            $recommendations[] = [
                'kos' => $kos,
                'score_cb' => $scoreCB,
                'score_cf' => $scoreCF,
                'score_hybrid' => $scoreHybrid,
                'match_percentage' => round($scoreHybrid * 100),
                'explanation' => $this->generateExplanation($kos, $profile, $scoreCF),
            ];
        }

        // Urutkan berdasarkan skor hybrid secara descending
        usort($recommendations, function ($a, $b) {
            return $b['score_hybrid'] <=> $a['score_hybrid'];
        });

        // Potong hasil sesuai limit (Top-N)
        $recommendations = array_slice($recommendations, 0, $limit);

        return [
            'is_cold_start' => $isColdStart,
            'rating_count' => $ratingCount,
            'alpha' => $this->combiner->effectiveAlpha($isColdStart),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Mendapatkan rekomendasi untuk satu kos tertentu secara spesifik (untuk detail kos)
     */
    public function getRecommendationForKos(int $userId, Kos $kos): array
    {
        $user = User::with('profile', 'interactions')->findOrFail($userId);
        
        $profile = $user->profile;
        if (!$profile) {
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'gender' => 'pria',
                'occupation' => 'mahasiswa',
                'budget_min' => 1000000,
                'budget_max' => 3000000,
                'preferred_facilities' => [],
                'preferred_rules' => [],
                'preferred_location' => 'Karawaci',
            ]);
        }

        $ratingCount = $user->interactions()->whereNotNull('rating')->count();
        $isColdStart = ($ratingCount === 0);

        $koses = collect([$kos]);

        $cbScores = $this->contentBasedFilter->calculateScores($profile, $koses);
        $cfScores = $isColdStart ? [] : $this->collaborativeFilter->calculateScores($userId, $koses);

        $scoreCB = $cbScores[$kos->id] ?? 0.0;
        $scoreCF = $isColdStart ? 0.0 : ($cfScores[$kos->id] ?? 0.0);

        $scoreHybrid = $this->combiner->combine($scoreCB, $scoreCF, $isColdStart);

        return [
            'score_cb' => $scoreCB,
            'score_cf' => $scoreCF,
            'score_hybrid' => $scoreHybrid,
            'match_percentage' => (int) round($scoreHybrid * 100),
            'explanation' => $this->generateExplanation($kos, $profile, $scoreCF),
        ];
    }

    /**
     * Hasilkan daftar alasan human-readable kenapa kos ini cocok/tidak cocok
     * untuk profil pengguna. Dipakai di panel trace rekomendasi admin.
     */
    protected function generateExplanation(Kos $kos, UserProfile $profile, float $scoreCF = 0.0): array
    {
        $reasons = [];

        $kosGender = strtolower($kos->gender_type);
        $userGender = strtolower($profile->gender);
        if (($kosGender === 'putri' && $userGender === 'pria') ||
            ($kosGender === 'putra' && $userGender === 'wanita')) {
            return ['Tidak sesuai gender: kos ini untuk ' . $kos->gender_type . ', profil pengguna ' . $profile->gender];
        }
        $reasons[] = 'Tipe kos (' . $kos->gender_type . ') sesuai dengan gender pengguna';

        $price = $kos->price;
        $min = $profile->budget_min ?? 1000000;
        $max = $profile->budget_max ?? 3000000;
        if ($price >= $min && $price <= $max) {
            $reasons[] = 'Harga Rp' . number_format($price, 0, ',', '.') . ' masuk dalam rentang budget pengguna (Rp' . number_format($min, 0, ',', '.') . ' - Rp' . number_format($max, 0, ',', '.') . ')';
        } else {
            $reasons[] = 'Harga Rp' . number_format($price, 0, ',', '.') . ' di luar rentang budget pengguna (Rp' . number_format($min, 0, ',', '.') . ' - Rp' . number_format($max, 0, ',', '.') . ')';
        }

        $reasons[] = 'Jarak ke kampus ' . $kos->distance_to_campus . ' km';

        $prefFacilities = $profile->preferred_facilities ?? [];
        $kosFacilities = $kos->facilities->pluck('name')->toArray();
        $matchedFacilities = array_values(array_intersect($prefFacilities, $kosFacilities));
        if (!empty($matchedFacilities)) {
            $reasons[] = 'Punya fasilitas yang dicari: ' . implode(', ', $matchedFacilities);
        }

        $prefRules = $profile->preferred_rules ?? [];
        $kosRules = $kos->rules->pluck('name')->toArray();
        $matchedRules = array_values(array_intersect($prefRules, $kosRules));
        if (!empty($matchedRules)) {
            $reasons[] = 'Aturan sesuai preferensi: ' . implode(', ', $matchedRules);
        }

        if ($scoreCF > 0) {
            $reasons[] = 'Disukai oleh pengguna lain dengan preferensi serupa (skor CF ' . round($scoreCF * 100) . '%)';
        }

        return $reasons;
    }
}
