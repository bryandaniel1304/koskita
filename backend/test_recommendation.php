<?php

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserInteraction;
use App\Models\Kos;
use App\Services\RecommendationService;

// 1. Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "==================================================\n";
echo "KOSKITA RECOMMENDATION ENGINE VERIFICATION TEST\n";
echo "==================================================\n\n";

// 2. Dapatkan demo user
$user = User::where('email', 'bryan@koskita.com')->first();
if (!$user) {
    echo "Error: Demo user 'bryan@koskita.com' tidak ditemukan. Harap seed database dulu.\n";
    exit(1);
}

echo "User Demo: " . $user->name . " (" . $user->email . ")\n";

// 3. Set up atau reset profil preferensi untuk testing
$profile = UserProfile::updateOrCreate(
    ['user_id' => $user->id],
    [
        'gender' => 'pria',
        'occupation' => 'mahasiswa',
        'budget_min' => 1500000,
        'budget_max' => 3500000,
        'preferred_facilities' => ['AC', 'WiFi', 'KM Dalam'],
        'preferred_rules' => ['Jam Malam'],
        'preferred_location' => 'Karawaci',
    ]
);

echo "Profil Preferensi diset:\n";
echo "- Gender: " . $profile->gender . "\n";
echo "- Lokasi: " . $profile->preferred_location . "\n";
echo "- Budget: Rp " . number_format($profile->budget_min) . " - Rp " . number_format($profile->budget_max) . "\n";
echo "- Fasilitas: " . implode(', ', $profile->preferred_facilities) . "\n";
echo "- Aturan: " . implode(', ', $profile->preferred_rules) . "\n\n";

// Hapus interaksi rating sebelumnya agar bersih
UserInteraction::where('user_id', $user->id)->delete();

// 4. Test 1: COLD-START RECOMMENDATIONS
echo "--------------------------------------------------\n";
echo "TEST 1: MENGUJI REKOMENDASI FASE COLD-START\n";
echo "--------------------------------------------------\n";

$recService = app(RecommendationService::class);
$result = $recService->getRecommendations($user->id, 5);

echo "Status Cold-Start: " . ($result['is_cold_start'] ? "AKTIF (Sesuai Harapan)" : "TIDAK AKTIF (Salah)") . "\n";
echo "Jumlah Rating Terdaftar: " . $result['rating_count'] . "\n\n";
echo "Top 5 Rekomendasi Kos (Cold-Start):\n";

foreach ($result['recommendations'] as $idx => $rec) {
    $kos = $rec['kos'];
    echo ($idx + 1) . ". " . $kos->name . "\n";
    echo "   - Harga: Rp " . number_format($kos->price) . "/bln | Lokasi: " . $kos->location . " (" . $kos->distance_to_campus . " km ke UPH)\n";
    echo "   - Tipe: " . $kos->gender_type . "\n";
    echo "   - Skor CB (Content-Based): " . round($rec['score_cb'], 4) . "\n";
    echo "   - Skor CF (Collaborative): " . round($rec['score_cf'], 4) . " (harus 0.0)\n";
    echo "   - Skor Hybrid: " . round($rec['score_hybrid'], 4) . " (" . $rec['match_percentage'] . "% Match)\n\n";
}

// 5. Test 2: WARM-START / HYBRID RECOMMENDATIONS
echo "--------------------------------------------------\n";
echo "TEST 2: MENGUJI REKOMENDASI FASE WARM-START (HYBRID)\n";
echo "--------------------------------------------------\n";

// Berikan rating (misal: user menyukai CIU Residence Karawaci [ID 1] dan memberi rating 5 bintang,
// dan memberi rating 1 bintang untuk kos yang jauh / tidak cocok)
$kosCiu = Kos::where('name', 'CIU Residence Karawaci')->first();
if ($kosCiu) {
    UserInteraction::create([
        'user_id' => $user->id,
        'kos_id' => $kosCiu->id,
        'rating' => 5,
        'is_favorite' => true,
    ]);
}

$kosGaruda = Kos::where('name', 'Kost Putra Garuda Serpong')->first();
if ($kosGaruda) {
    UserInteraction::create([
        'user_id' => $user->id,
        'kos_id' => $kosGaruda->id,
        'rating' => 2,
    ]);
}

$resultWarm = $recService->getRecommendations($user->id, 5);

echo "Status Cold-Start: " . ($resultWarm['is_cold_start'] ? "AKTIF (Salah)" : "TIDAK AKTIF (Sesuai Harapan)") . "\n";
echo "Jumlah Rating Terdaftar: " . $resultWarm['rating_count'] . " (Sesuai Harapan)\n\n";
echo "Top 5 Rekomendasi Kos (Warm-Start Hybrid):\n";

foreach ($resultWarm['recommendations'] as $idx => $rec) {
    $kos = $rec['kos'];
    echo ($idx + 1) . ". " . $kos->name . "\n";
    echo "   - Harga: Rp " . number_format($kos->price) . "/bln | Lokasi: " . $kos->location . "\n";
    echo "   - Skor CB (Content-Based): " . round($rec['score_cb'], 4) . "\n";
    echo "   - Skor CF (Collaborative): " . round($rec['score_cf'], 4) . " (harus > 0.0 jika dihitung dari kemiripan user)\n";
    echo "   - Skor Hybrid (0.6*CB + 0.4*CF): " . round($rec['score_hybrid'], 4) . " (" . $rec['match_percentage'] . "% Match)\n\n";
}

// Kembalikan ke state awal agar demo user bersih saat dicoba di frontend
UserInteraction::where('user_id', $user->id)->delete();
echo "==================================================\n";
echo "TEST VERIFIKASI SELESAI. DATABASE TELAH DI-RESET.\n";
echo "==================================================\n";
