<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\Rule;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Database\Seeder;

/**
 * Perluasan lanjutan khusus sub-area Binong / Taman Permata Millenium
 * (kelurahan Binong, Kec. Curug, Kab. Tangerang) -- diminta user setelah
 * melihat area itu di Google Maps. SAMA seperti ExpandedKosSeeder: data
 * SINTETIS, bukan hasil scraping Google/Maps/Mamikos dkk (lihat komentar
 * di ExpandedKosSeeder.php untuk alasan lengkap: ToS platform lain, hak
 * cipta foto, privasi kontak pemilik asli, dan skala "ratusan+" ke DB
 * live melewati batas wajar sample akademis).
 *
 * Binong/Taman Permata Millenium SECARA GEOGRAFIS masih bagian dari
 * kawasan Lippo Karawaci yang sama (+-5 menit dari kampus UPH, dekat
 * Siloam Hospital) -- jadi tetap disimpan dengan location="Karawaci"
 * (konsisten dengan filter & fallback peta yang sudah ada), hanya
 * koordinat & label nama yang dibuat lebih spesifik ke sub-area ini.
 *
 * Jalankan: `php artisan db:seed --class=BinongKosSeeder`
 */
class BinongKosSeeder extends Seeder
{
    /** Titik tengah perkiraan Taman Permata Millenium / Binong, Curug --
     *  sekitar Jl. Permata Indah, dekat Lippo Karawaci & Siloam Hospital. */
    protected array $center = [-6.2145, 106.5945];

    protected array $namePrefixes = ['Kos', 'Kost', 'Griya', 'Wisma', 'Pondok', 'Rumah Kos', 'Homestay'];

    protected array $nameDescriptors = [
        'Melati', 'Anggrek', 'Mawar', 'Dahlia', 'Cempaka', 'Kenanga', 'Teratai', 'Seruni',
        'Asri', 'Indah', 'Mandiri', 'Harmoni', 'Damai', 'Ceria', 'Permai', 'Sejati', 'Berkah',
    ];

    protected array $areaLabels = ['Binong', 'Taman Permata Millenium', 'Permata Sari', 'Permata Indah', 'Curug'];

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

        $count = 22;
        $usedNames = Kos::pluck('name')->flip();
        $newKoses = [];

        [$centerLat, $centerLng] = $this->center;

        for ($i = 0; $i < $count; $i++) {
            $name = $this->generateUniqueName($usedNames);
            $usedNames[$name] = true;

            // Klaster lebih rapat dari ExpandedKosSeeder (~100-900m) karena
            // ini 1 kompleks/sub-area spesifik, bukan area seluas Karawaci.
            $lat = $centerLat + $this->jitter();
            $lng = $centerLng + $this->jitter();
            $distanceToCampus = round(mt_rand(15, 40) / 10, 1); // ~1.5 - 4 km ke UPH

            $facilityCount = mt_rand(2, 6);
            $selectedFacilities = collect($facilityNames)->shuffle()->take($facilityCount)->all();
            $selectedRules = collect($ruleNames)->shuffle()->take(mt_rand(1, 3))->all();

            $basePrice = 950000 + ($facilityCount * 250000);
            $price = $basePrice + (mt_rand(-3, 10) * 100000);
            $price = max(800000, $price);

            $genderType = collect(['putra', 'putri', 'campur', 'campur'])->random();
            $totalRooms = mt_rand(1, 10);

            $kos = Kos::create([
                'name' => $name,
                'price' => $price,
                'gender_type' => $genderType,
                'location' => 'Karawaci', // tetap 3 bucket kanonik yang sudah dipakai filter/UI
                'latitude' => round($lat, 6),
                'longitude' => round($lng, 6),
                'distance_to_campus' => $distanceToCampus,
                'total_rooms' => $totalRooms,
                'description' => $this->generateDescription($name, $selectedFacilities),
                'image_url' => collect($this->imagePool)->random(),
            ]);

            $kos->facilities()->sync(collect($selectedFacilities)->map(fn ($n) => $facilities[$n])->all());
            $kos->rules()->sync(collect($selectedRules)->map(fn ($n) => $rules[$n])->all());

            $newKoses[] = $kos;
        }

        $this->command?->info(count($newKoses) . ' kos baru (Binong / Taman Permata Millenium) berhasil dibuat.');

        $this->seedInteractions($newKoses);
    }

    protected function generateUniqueName($usedNames): string
    {
        do {
            $prefix = collect($this->namePrefixes)->random();
            $descriptor = collect($this->nameDescriptors)->random();
            $areaLabel = collect($this->areaLabels)->random();
            $name = "$prefix $descriptor $areaLabel";
        } while (isset($usedNames[$name]));

        return $name;
    }

    protected function jitter(): float
    {
        // -0.008 s/d 0.008 derajat (~ -900m s/d +900m) -- lebih rapat
        // daripada ExpandedKosSeeder karena mewakili 1 sub-area spesifik.
        return (mt_rand(-80, 80) / 10000);
    }

    protected function generateDescription(string $name, array $facilities): string
    {
        $areaLabel = collect($this->areaLabels)->random();
        $facilityText = count($facilities) > 0 ? implode(', ', array_slice($facilities, 0, 3)) : 'fasilitas standar';
        $vibes = collect([
            'Lingkungan perumahan tenang, dekat Jl. Permata Indah.',
            'Akses mudah ke Lippo Karawaci & Siloam Hospital, sekitar 5 menit ke UPH.',
            'Kompleks perumahan asri, cocok untuk mahasiswa maupun pekerja.',
            'Dekat minimarket dan tempat makan di sekitar Curug/Binong.',
            'Dikelola langsung oleh pemilik, respons cepat kalau ada kendala.',
        ])->random();

        return "$name berlokasi di kawasan $areaLabel (Curug, Tangerang), dilengkapi $facilityText. $vibes";
    }

    protected function seedInteractions(array $newKoses): void
    {
        $users = User::where('role', 'user')->with('profile')->get()->filter(fn ($u) => $u->profile !== null);

        $created = 0;
        foreach ($users as $user) {
            $profile = $user->profile;

            if (mt_rand(1, 100) > 75) {
                continue;
            }

            $ratedKoses = collect($newKoses)->random(min(count($newKoses), mt_rand(2, 6)));

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

        $this->command?->info("$created interaksi pengguna dibuat untuk kos Binong/Taman Permata Millenium.");
    }
}
