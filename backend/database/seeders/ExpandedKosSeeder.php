<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\Rule;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Database\Seeder;

/**
 * Perluasan data kos SINTETIS (bukan hasil scraping platform lain -- lihat
 * diskusi sebelumnya soal ToS/hak cipta/privasi kalau data itu diambil
 * mentah dari Mamikos dkk). Nama kos di sini fiktif (pola penamaan umum ala
 * kos Indonesia), tapi koordinatnya disebar realistis di sekitar area asli
 * yang diminta -- diprioritaskan Karawaci (UPH/Lippo Village/Permata),
 * disusul BSD dan Gading Serpong/Serpong -- supaya dataset cukup besar
 * (150+ kos total) untuk skripsi menguji algoritma rekomendasi dengan
 * layak (cold-start & warm-start, variasi harga/fasilitas/lokasi).
 *
 * Jalankan terpisah dari DatabaseSeeder utama (yang sudah pernah dijalankan
 * & tidak idempotent -- re-run akan gagal karena email admin/demo user
 * sudah ada): `php artisan db:seed --class=ExpandedKosSeeder`
 */
class ExpandedKosSeeder extends Seeder
{
    /**
     * Titik tengah tiap area -- Karawaci sengaja dianchor ke sekitar
     * kampus UPH & Lippo Village (sesuai prioritas yang diminta), bukan
     * cuma "Karawaci" secara umum.
     */
    protected array $areaCenters = [
        'Karawaci' => [-6.2088, 106.6003],
        'BSD' => [-6.3021, 106.6527],
        'Serpong' => [-6.3208, 106.6714],
    ];

    protected array $namePrefixes = ['Kos', 'Kost', 'Griya', 'Wisma', 'Pondok', 'Rumah Kos', 'Homestay', 'Villa'];

    protected array $nameDescriptors = [
        'Melati', 'Anggrek', 'Mawar', 'Dahlia', 'Cempaka', 'Kenanga', 'Flamboyan', 'Kamboja', 'Teratai', 'Seruni',
        'Asri', 'Indah', 'Sejahtera', 'Mandiri', 'Harmoni', 'Damai', 'Sentosa', 'Ceria', 'Merdeka', 'Barokah',
        'Amanah', 'Bersama', 'Nyaman', 'Bahagia', 'Permai', 'Elok', 'Selaras', 'Utama', 'Sejati', 'Berkah',
    ];

    /** Label area yang dipakai di NAMA kos (bukan kolom location) -- boleh
     *  lebih spesifik dari 3 area kanonik (UPH/Permata/Lippo Village semua
     *  tetap tersimpan sebagai location="Karawaci" supaya filter & peta
     *  fallback yang sudah ada tidak perlu diubah). */
    protected array $areaLabelsByLocation = [
        'Karawaci' => ['UPH', 'Permata', 'Lippo Village', 'Karawaci'],
        'BSD' => ['BSD', 'BSD City'],
        'Serpong' => ['Gading Serpong', 'Serpong'],
    ];

    protected array $imagePool = [
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1556020685-ae41abfc9365?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598928636135-d146006ff4be?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1616046229478-9901c5536a45?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1554995207-c18c203602cb?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=500&q=80',
    ];

    public function run(): void
    {
        $facilities = Facility::pluck('id', 'name');
        $rules = Rule::pluck('id', 'name');
        $facilityNames = $facilities->keys()->all();
        $ruleNames = $rules->keys()->all();

        if ($facilities->isEmpty() || $rules->isEmpty()) {
            $this->command?->error('Master data fasilitas/aturan kosong -- jalankan DatabaseSeeder utama dulu.');
            return;
        }

        // Prioritas: Karawaci (UPH/Lippo Village/Permata) jauh lebih banyak
        // daripada BSD & Gading Serpong, sesuai permintaan.
        $plan = [
            'Karawaci' => 70,
            'BSD' => 30,
            'Serpong' => 30,
        ];

        $usedNames = Kos::pluck('name')->flip();
        $newKoses = [];

        foreach ($plan as $location => $count) {
            [$centerLat, $centerLng] = $this->areaCenters[$location];
            $areaLabels = $this->areaLabelsByLocation[$location];

            for ($i = 0; $i < $count; $i++) {
                $name = $this->generateUniqueName($areaLabels, $usedNames);
                $usedNames[$name] = true;

                // Sebar ~200-1800m dari titik tengah area (derajat lat/lng
                // ~111km, jadi 0.002-0.016 derajat) -- realistis untuk
                // klaster kos di sekitar satu kampus/area, tidak menumpuk
                // persis di satu titik.
                $lat = $centerLat + $this->jitter();
                $lng = $centerLng + $this->jitter();
                $distanceToCampus = round(mt_rand(2, 35) / 10, 1); // 0.2 - 3.5 km

                $facilityCount = mt_rand(2, 6);
                $selectedFacilities = collect($facilityNames)->shuffle()->take($facilityCount)->all();
                $selectedRules = collect($ruleNames)->shuffle()->take(mt_rand(1, 3))->all();

                // Harga naik kasar mengikuti jumlah fasilitas + sedikit acak,
                // supaya tidak semua kos harganya seragam datar.
                $basePrice = 900000 + ($facilityCount * 250000);
                $price = $basePrice + (mt_rand(-3, 10) * 100000);
                $price = max(800000, $price);

                $genderType = collect(['putra', 'putri', 'campur', 'campur'])->random();
                $totalRooms = mt_rand(1, 10);

                $kos = Kos::create([
                    'name' => $name,
                    'price' => $price,
                    'gender_type' => $genderType,
                    'location' => $location,
                    'latitude' => round($lat, 6),
                    'longitude' => round($lng, 6),
                    'distance_to_campus' => $distanceToCampus,
                    'total_rooms' => $totalRooms,
                    'description' => $this->generateDescription($name, $areaLabels, $selectedFacilities),
                    'image_url' => collect($this->imagePool)->random(),
                ]);

                $kos->facilities()->sync(collect($selectedFacilities)->map(fn ($n) => $facilities[$n])->all());
                $kos->rules()->sync(collect($selectedRules)->map(fn ($n) => $rules[$n])->all());

                $newKoses[] = $kos;
            }
        }

        $this->command?->info(count($newKoses) . ' kos baru berhasil dibuat (' . implode(', ', array_map(fn ($l, $c) => "$l: $c", array_keys($plan), $plan)) . ').');

        $this->seedInteractions($newKoses);
    }

    protected function generateUniqueName(array $areaLabels, $usedNames): string
    {
        do {
            $prefix = collect($this->namePrefixes)->random();
            $descriptor = collect($this->nameDescriptors)->random();
            $areaLabel = collect($areaLabels)->random();
            $name = "$prefix $descriptor $areaLabel";
        } while (isset($usedNames[$name]));

        return $name;
    }

    protected function jitter(): float
    {
        // Angka acak antara -0.016 dan 0.016 derajat (~ -1.8km s/d +1.8km).
        return (mt_rand(-160, 160) / 10000);
    }

    protected function generateDescription(string $name, array $areaLabels, array $facilities): string
    {
        $areaLabel = collect($areaLabels)->random();
        $facilityText = count($facilities) > 0 ? implode(', ', array_slice($facilities, 0, 3)) : 'fasilitas standar';
        $vibes = collect([
            'Lingkungan tenang dan aman, cocok untuk mahasiswa maupun pekerja.',
            'Akses mudah ke jalan utama, dekat minimarket dan tempat makan.',
            'Bangunan baru direnovasi, kebersihan terjaga.',
            'Dikelola langsung oleh pemilik, respons cepat kalau ada kendala.',
            'Cocok untuk yang cari kos dekat kampus tanpa ribet macet.',
        ])->random();

        return "$name berlokasi di kawasan $areaLabel, dilengkapi $facilityText. $vibes";
    }

    /**
     * Buat interaksi (rating/klik/favorit) dari user penyewa yang sudah ada
     * terhadap kos-kos baru ini -- pakai heuristik yang sama seperti
     * DatabaseSeeder utama (cocok gender/budget/lokasi -> rating lebih
     * tinggi), supaya Collaborative Filtering benar-benar punya sinyal
     * nyata buat diuji, bukan cuma Content-Based yang jalan.
     */
    protected function seedInteractions(array $newKoses): void
    {
        $users = User::where('role', 'user')->with('profile')->get()->filter(fn ($u) => $u->profile !== null);

        $created = 0;
        foreach ($users as $user) {
            $profile = $user->profile;

            // Sebagian user sengaja tidak dikasih interaksi ke kos baru sama
            // sekali -- supaya variasi cold-start/warm-start tetap ada,
            // bukan semua user jadi "warm" sekaligus.
            if (mt_rand(1, 100) > 75) {
                continue;
            }

            $ratedKoses = collect($newKoses)->random(min(count($newKoses), mt_rand(3, 10)));

            foreach ($ratedKoses as $kos) {
                if (($kos->gender_type === 'putra' && $profile->gender === 'wanita') ||
                    ($kos->gender_type === 'putri' && $profile->gender === 'pria')) {
                    $rating = mt_rand(1, 2);
                } else {
                    $budgetMatch = $kos->price >= $profile->budget_min && $kos->price <= $profile->budget_max;
                    $locationMatch = $kos->location === $profile->preferred_location;

                    if ($budgetMatch && $locationMatch) {
                        $rating = mt_rand(4, 5);
                    } elseif ($budgetMatch || $locationMatch) {
                        $rating = mt_rand(3, 4);
                    } else {
                        $rating = mt_rand(2, 3);
                    }
                }

                UserInteraction::updateOrCreate(
                    ['user_id' => $user->id, 'kos_id' => $kos->id],
                    [
                        'rating' => $rating,
                        'is_favorite' => $rating >= 4 && mt_rand(0, 1) === 1,
                        'click_count' => mt_rand(1, 6),
                    ]
                );
                $created++;
            }
        }

        $this->command?->info("$created interaksi pengguna dibuat untuk kos baru.");
    }
}
