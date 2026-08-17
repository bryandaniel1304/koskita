<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\KosImage;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Ganti seluruh isi tabel `koses` (yang sebelumnya data karangan/seed) dengan
 * data nyata hasil riset `places:search-lodging` + `mamikos:scrape`.
 *
 * PRINSIP UTAMA: hanya kos yang punya HARGA ASLI dari Mamikos yang diimpor
 * -- kandidat dari Google Places yang tidak berhasil dicocokkan dengan
 * listing Mamikos (jadi tidak ada harga/fasilitas riil) TIDAK diimpor,
 * daripada diisi harga 0/karangan. Google Places dipakai sebagai pelengkap
 * (foto asli + rating) lewat pencocokan nama, bukan sumber utama.
 *
 * Data yang TIDAK tersedia dari kedua sumber (peraturan kos / rules) sengaja
 * dibiarkan kosong -- lihat catatan di akhir output command.
 *
 * Koordinat kampus UPH Karawaci dipakai sebagai titik acuan distance_to_campus
 * (garis lurus/haversine, BUKAN jarak rute riil -- Distance Matrix API belum
 * diaktifkan di project ini).
 */
#[Signature('places:import-koses {--area=all : karawaci|bsd|serpong|all} {--dry-run : Tampilkan hasil pencocokan tanpa mengubah database}')]
#[Description('Gabungkan hasil places:search-lodging + mamikos:scrape, lalu GANTI seluruh tabel koses dengan data nyata (harga wajib ada, sisanya dari Google Places sebagai pelengkap foto/rating).')]
class ImportRealKoses extends Command
{
    protected const AREAS = ['karawaci', 'bsd', 'serpong'];

    protected const AREA_LABEL = [
        'karawaci' => 'Karawaci',
        'bsd' => 'BSD City',
        'serpong' => 'Serpong',
    ];

    // Kampus Universitas Pelita Harapan Karawaci -- dipakai sebagai titik
    // acuan distance_to_campus (hasil pencarian Places API text search).
    protected const CAMPUS_LAT = -6.228373;
    protected const CAMPUS_LNG = 106.611269;

    // Nama kos Indonesia pendek & banyak kata umum ("kost", "residence",
    // nama area) sehingga similar_text() gampang false-positive di ambang
    // rendah -- lihat catatan di findBestMatch(). Dua jalur diterima:
    // (a) sangat mirip secara teks (>=NAME_MATCH_HIGH) tanpa syarat jarak,
    // (b) cukup mirip (>=NAME_MATCH_LOW) TAPI koordinatnya juga berdekatan.
    protected const NAME_MATCH_HIGH = 85.0;
    protected const NAME_MATCH_LOW = 65.0;
    protected const MAX_MATCH_DISTANCE_KM = 0.8;

    public function handle(GoogleMapsService $maps): int
    {
        $areaOption = $this->option('area');
        $areas = $areaOption === 'all' ? self::AREAS : array_intersect(self::AREAS, [$areaOption]);
        $dryRun = (bool) $this->option('dry-run');

        if (empty($areas)) {
            $this->error("Area '$areaOption' tidak dikenal. Pilihan: " . implode(', ', self::AREAS) . ', all.');
            return self::FAILURE;
        }

        $dir = storage_path('app/research');
        $combined = [];
        $unmatchedCount = 0;

        foreach ($areas as $area) {
            $mamikosFile = $dir . DIRECTORY_SEPARATOR . "mamikos-{$area}.json";
            $placesFile = $dir . DIRECTORY_SEPARATOR . "lodging-{$area}-filtered.json";

            if (!File::exists($mamikosFile)) {
                $this->warn("Lewati area '$area': $mamikosFile tidak ada.");
                continue;
            }

            $mamikosListings = json_decode(File::get($mamikosFile), true) ?? [];
            $placesListings = File::exists($placesFile) ? (json_decode(File::get($placesFile), true) ?? []) : [];

            $this->info(self::AREA_LABEL[$area] . ": {$this->pluralCount($mamikosListings)} listing Mamikos, "
                . "{$this->pluralCount($placesListings)} kandidat Google Places.");

            foreach ($mamikosListings as $m) {
                $match = $this->findBestMatch($m['name'], $m['lat'], $m['lng'], $placesListings);
                if ($match) {
                    $this->line('  [cocok ' . round($match['score']) . "%] \"{$m['name']}\" <-> \"{$match['place']['name']}\"");
                } else {
                    $unmatchedCount++;
                }

                $combined[] = [
                    'area' => $area,
                    'name' => $this->cleanName($m['name']),
                    'price' => $m['price_monthly'],
                    'gender_type' => $m['gender'] ?? 'campur',
                    'lat' => $match['place']['lat'] ?? $m['lat'],
                    'lng' => $match['place']['lng'] ?? $m['lng'],
                    'facilities' => $m['facilities'],
                    'place_id' => $match['place']['place_id'] ?? null,
                    'rating' => $match['place']['rating'] ?? null,
                    'fallback_image_url' => $m['image_url'] ?? null,
                    'source_url' => $m['source_url'],
                ];
            }
        }

        $this->newLine();
        $this->comment(count($combined) . ' kos siap diimpor (' . (count($combined) - $unmatchedCount)
            . ' dapat foto asli dari Google Places, ' . $unmatchedCount . ' pakai foto dari Mamikos).');

        if ($dryRun) {
            $this->comment('--dry-run aktif, database TIDAK diubah.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Lanjut ganti seluruh tabel koses dengan ' . count($combined) . ' data ini? Data lama (booking/review/interaksi terkait) akan terhapus.', false)) {
            $this->comment('Dibatalkan.');
            return self::SUCCESS;
        }

        $this->replaceDatabase($combined, $maps);

        return self::SUCCESS;
    }

    protected function pluralCount(array $arr): int
    {
        return count($arr);
    }

    /**
     * Cari kandidat Google Places yang SANGAT MUNGKIN properti yang sama
     * dengan listing Mamikos -- bukan cuma mirip nama. Nama kos Indonesia
     * banyak memakai kata umum (kost/residence/nama area) sehingga
     * similar_text() gampang false-positive kalau cuma mengandalkan teks
     * (mis. "Kost Gerendeng" vs "Kost LA Residence" bisa dapat ~48% padahal
     * jelas properti berbeda) -- karena itu jarak koordinat jadi syarat
     * kedua, bukan sekadar skor tertinggi yang diambil begitu saja.
     */
    protected function findBestMatch(string $mamikosName, ?float $mLat, ?float $mLng, array $placesListings): ?array
    {
        $needle = $this->normalizeName($mamikosName);
        $best = null;
        $bestScore = 0;

        foreach ($placesListings as $place) {
            $hay = $this->normalizeName($place['name'] ?? '');
            if ($hay === '') {
                continue;
            }
            similar_text($needle, $hay, $percent);
            if ($percent <= $bestScore) {
                continue;
            }

            $distance = $this->haversineKm($mLat ?? 0, $mLng ?? 0, $place['lat'] ?? null, $place['lng'] ?? null);
            $qualifies = $percent >= self::NAME_MATCH_HIGH
                || ($percent >= self::NAME_MATCH_LOW && $distance <= self::MAX_MATCH_DISTANCE_KM);

            if ($qualifies) {
                $bestScore = $percent;
                $best = $place;
            }
        }

        return $best ? ['place' => $best, 'score' => $bestScore] : null;
    }

    /** Buang kata generik (kost/kos/tipe a/murah/eksklusif/nama kota) supaya perbandingan fokus ke nama unik kos-nya. */
    protected function normalizeName(string $name): string
    {
        $noise = ['kost', 'kos', 'tipe a', 'tipe b', 'tipe c', 'murah', 'eksklusif', 'putra', 'putri',
            'campur', 'tangerang', 'selatan', 'karawaci', 'serpong', 'bsd', 'city', 'lippo', 'village', '-'];
        $clean = mb_strtolower($name);
        $clean = str_replace($noise, ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean);
    }

    protected function cleanName(string $name): string
    {
        return trim(preg_replace('/\s*-\s*MamiKos\s*$/i', '', $name));
    }

    protected function replaceDatabase(array $combined, GoogleMapsService $maps): void
    {
        $owners = User::where('role', 'owner')->pluck('id')->values();
        if ($owners->isEmpty()) {
            $this->error('Tidak ada user dengan role owner -- batal, tidak ada yang bisa dijadikan pemilik kos.');
            return;
        }

        DB::transaction(function () use ($combined, $owners, $maps) {
            $deleted = Kos::count();
            Kos::query()->delete(); // cascade ke bookings/reviews/interactions/room_types/images/waitlists/pivot
            $this->info("$deleted kos lama dihapus (beserta data terkait via cascade).");

            $ownerIndex = 0;
            $imported = 0;
            $photosDownloaded = 0;

            foreach ($combined as $i => $item) {
                $distance = $this->haversineKm(self::CAMPUS_LAT, self::CAMPUS_LNG, $item['lat'], $item['lng']);

                $kos = Kos::create([
                    'owner_id' => $owners[$ownerIndex % $owners->count()],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'gender_type' => $item['gender_type'],
                    'location' => self::AREA_LABEL[$item['area']],
                    'latitude' => $item['lat'],
                    'longitude' => $item['lng'],
                    'distance_to_campus' => round($distance, 2),
                    'total_rooms' => 4, // Mamikos tidak expose kapasitas total, estimasi wajar skala kos rumahan
                    'description' => "Data diimpor dari riset Google Places + Mamikos (" . now()->format('Y-m-d') . ")."
                        . ($item['rating'] ? " Rating Google: {$item['rating']}." : ''),
                    'verified_at' => null, // belum diverifikasi admin secara manual
                ]);
                $ownerIndex++;
                $imported++;

                // Fasilitas: findOrCreate per nama, lalu attach.
                $facilityIds = collect($item['facilities'])
                    ->unique()
                    ->map(fn ($name) => Facility::firstOrCreate(['name' => $name])->id);
                $kos->facilities()->sync($facilityIds);

                // Foto: prioritaskan foto asli Google Places (place_id ada),
                // fallback ke og:image halaman Mamikos.
                $photoPath = $item['place_id']
                    ? $this->downloadGooglePhoto($item['place_id'], $maps)
                    : null;
                if (!$photoPath && $item['fallback_image_url']) {
                    $photoPath = $this->downloadFromUrl($item['fallback_image_url']);
                }

                if ($photoPath) {
                    KosImage::create([
                        'kos_id' => $kos->id,
                        'path' => $photoPath,
                        'is_cover' => true,
                        'sort_order' => 0,
                    ]);
                    $photosDownloaded++;
                }

                $this->line('  [' . ($i + 1) . '/' . count($combined) . "] {$item['name']} tersimpan"
                    . ($photoPath ? ' (+foto)' : ' (tanpa foto)'));
            }

            $this->newLine();
            $this->info("Selesai: $imported kos diimpor, $photosDownloaded dapat foto.");
        });
    }

    /** Jarak garis lurus (haversine), km -- BUKAN jarak rute jalan sebenarnya. */
    protected function haversineKm(float $lat1, float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat2 === null || $lng2 === null) {
            return 0;
        }
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    protected function downloadGooglePhoto(string $placeId, GoogleMapsService $maps): ?string
    {
        try {
            $photoName = $maps->firstPhotoName($placeId);
            if (!$photoName) {
                return null;
            }
            $bytes = $maps->downloadPhoto($photoName);
            if (!$bytes) {
                return null;
            }
            $filename = 'kos-images/' . uniqid('gp_') . '.jpg';
            Storage::disk('public')->put($filename, $bytes);
            return $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function downloadFromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return null;
            }
            $filename = 'kos-images/' . uniqid('mk_') . '.jpg';
            Storage::disk('public')->put($filename, $response->body());
            return $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
