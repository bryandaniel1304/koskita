<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Kos;
use App\Models\KosImage;
use App\Models\Rule;
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
 * data nyata hasil riset multi-platform: `mamikos:scrape` + `rukita:scrape`
 * (bisa nambah platform lain nanti tinggal tambah entri PLATFORM_FILES),
 * dilengkapi `places:search-lodging` (Google Places) untuk foto/rating.
 *
 * PRINSIP UTAMA: hanya kos yang punya HARGA ASLI dari salah satu platform
 * yang diimpor -- kandidat dari Google Places yang tidak berhasil
 * dicocokkan (jadi tidak ada harga/fasilitas riil) TIDAK diimpor, daripada
 * diisi harga 0/karangan. Google Places dipakai sebagai pelengkap (foto
 * asli + rating) lewat pencocokan nama, bukan sumber utama.
 *
 * Peraturan kos (rules) dulu selalu kosong karena tidak ada sumber yang
 * mengeksposnya; sejak Infokost masuk, listing dari sana membawa peraturan
 * asli (aturan bertamu/menginap, boleh bawa hewan, dsb) sehingga kolom itu
 * kini terisi untuk sebagian kos. Listing dari platform yang tetap tidak
 * menyediakannya dibiarkan kosong, bukan diisi nilai bawaan.
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

    // Prefix nama berkas storage/app/research/{prefix}-{area}.json per
    // platform -- tambah baris di sini kalau nanti ada platform baru
    // (mis. 'papikost' => 'Papikost') yang sudah punya scraper sendiri.
    protected const PLATFORM_FILES = [
        'mamikos' => 'Mamikos',
        'rukita' => 'Rukita',
        'infokost' => 'Infokost',
    ];

    /**
     * Penyeragaman nama fasilitas lintas platform.
     *
     * Tiga sumber menamai hal yang sama dengan tiga cara ("Kamar Mandi
     * Dalam" / "K. Mandi Dalam" / "KM Dalam"), sehingga tanpa peta ini
     * tabel facilities membengkak jadi 125 entri penuh kembaran. Dampaknya
     * bukan cuma daftar filter yang berantakan: ContentBasedFilter
     * mencocokkan preferensi dengan in_array() string-exact, jadi kos yang
     * menulis "K. Mandi Dalam" TIDAK pernah cocok dengan pengguna yang
     * memilih "Kamar Mandi Dalam" -- skornya terpecah tanpa ada yang sadar.
     *
     * Yang SENGAJA tidak digabung: "Dapur Bersama" vs "Dapur Pribadi"
     * (berbagi vs sendiri itu beda nyata bagi penyewa), dan ukuran kasur
     * hanya diseragamkan ke jenisnya, bukan dihapus.
     */
    protected const FACILITY_ALIASES = [
        'k. mandi dalam' => 'Kamar Mandi Dalam',
        'km dalam' => 'Kamar Mandi Dalam',
        'k. mandi luar' => 'Kamar Mandi Luar',
        'km luar' => 'Kamar Mandi Luar',
        'kamar mandi luar - wc duduk' => 'Kamar Mandi Luar',
        'kamar mandi luar - wc jongkok' => 'Kamar Mandi Luar',
        'lemari / storage' => 'Lemari',
        'lemari pakaian' => 'Lemari',
        'r. tamu' => 'Ruang Tamu',
        'r. makan' => 'Area Makan',
        'r. jemur' => 'Area Jemur',
        'jemuran' => 'Area Jemur',
        'cleaning service' => 'Cleaning',
        'musholla' => 'Mushola',
        'balcon' => 'Balkon/Teras',
        'balkon' => 'Balkon/Teras',
        'wifi' => 'WiFi',
        'kartu akses masuk' => 'Kartu Akses',
        'parkir motor & sepeda' => 'Parkir Motor',
        'parkir motor (di luar unit)' => 'Parkir Motor',
        'parkir mobil (di luar unit)' => 'Parkir Mobil',
        'single bed 90x200 cm' => 'Single Bed',
        'single bed 100x200 cm' => 'Single Bed',
        'full size bed 120x200 cm' => 'Full Size Bed',
        'queen bed 160x200 cm' => 'Queen Bed',
        'king bed 180x200 cm' => 'King Bed',
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

        foreach ($areas as $area) {
            $placesFile = $dir . DIRECTORY_SEPARATOR . "lodging-{$area}-filtered.json";
            $placesListings = File::exists($placesFile) ? (json_decode(File::get($placesFile), true) ?? []) : [];

            foreach (self::PLATFORM_FILES as $prefix => $platformLabel) {
                $platformFile = $dir . DIRECTORY_SEPARATOR . "{$prefix}-{$area}.json";
                if (!File::exists($platformFile)) {
                    $this->warn("Lewati $platformLabel area '$area': $platformFile tidak ada.");
                    continue;
                }

                $listings = json_decode(File::get($platformFile), true) ?? [];
                $this->info(self::AREA_LABEL[$area] . " ($platformLabel): {$this->pluralCount($listings)} listing, "
                    . "{$this->pluralCount($placesListings)} kandidat Google Places.");

                foreach ($listings as $m) {
                    if (empty($m['price_monthly'])) {
                        continue; // tanpa harga asli, tidak diimpor (lihat prinsip utama di docblock)
                    }

                    $match = $this->findBestMatch($m['name'], $m['lat'], $m['lng'], $placesListings);
                    if ($match) {
                        $this->line('  [cocok ' . round($match['score']) . "%] \"{$m['name']}\" <-> \"{$match['place']['name']}\"");
                    }

                    $combined[] = [
                        'area' => $area,
                        'platform' => $platformLabel,
                        'name' => $this->cleanName($m['name']),
                        // Nama tipe kamar dibawa terpisah dari nama kos --
                        // inilah yang jadi baris kos_room_types saat beberapa
                        // listing dari gedung yang sama dilebur di mergeVariant().
                        'room_type' => $m['room_type'] ?? null,
                        'price' => $m['price_monthly'],
                        'gender_type' => $this->resolveGender($m['gender'] ?? 'campur', $m['name']),
                        'lat' => $match['place']['lat'] ?? $m['lat'],
                        'lng' => $match['place']['lng'] ?? $m['lng'],
                        'facilities' => $m['facilities'] ?? [],
                        'place_id' => $match['place']['place_id'] ?? null,
                        'rating' => $match['place']['rating'] ?? null,
                        'rules' => $m['rules'] ?? [],
                        'image_local' => $m['image_local'] ?? null,
                        'street_address' => $m['street_address'] ?? null,
                        'fallback_image_url' => $m['image_url'] ?? null,
                        'source_url' => $m['source_url'],
                    ];
                }
            }
        }

        $combined = $this->dedupeByNameAndProximity($combined);

        // Dihitung ulang dari $combined SETELAH dedupe (bukan dari $unmatchedCount yang
        // ditally selagi loop per-listing mentah) -- kalau dari counter lama, entri yang
        // hilang lewat dedupeByNameAndProximity() bikin totalnya tidak nyambung lagi.
        $withGooglePhoto = collect($combined)->whereNotNull('place_id')->count();

        $this->newLine();
        $this->comment(count($combined) . ' kos siap diimpor (setelah dedupe lintas platform), '
            . "$withGooglePhoto dapat foto asli dari Google Places, "
            . (count($combined) - $withGooglePhoto) . ' pakai foto dari platform asalnya masing-masing).');

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

    /** Petakan nama fasilitas ke bentuk bakunya; yang tidak terdaftar dipakai apa adanya (cuma dirapikan spasinya). */
    protected function canonicalFacility(string $name): string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));

        return self::FACILITY_ALIASES[$key] ?? trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Tentukan gender kos. Platform yang tidak memasang penanda gender
     * dianggap "campur" oleh scraper-nya, padahal namanya sering sudah
     * menyebutkan dengan jelas ("Disewakan Kamar Kost Khusus Wanita",
     * "Kos Pak Chris Graha Bunga (Pria)").
     *
     * Ini bukan sekadar kerapian tampilan: gender adalah BATASAN KERAS di
     * RecommendationService -- salah label berarti kos khusus putri ikut
     * direkomendasikan ke penyewa pria.
     *
     * Hanya menimpa nilai 'campur' (yang artinya "tidak dinyatakan"), tidak
     * pernah menimpa penanda eksplisit dari platform.
     */
    protected function resolveGender(string $platformGender, string $name): string
    {
        if ($platformGender !== 'campur') {
            return $platformGender;
        }

        if (preg_match('/\b(putri|wanita|perempuan|muslimah|cewek)\b/i', $name)) {
            return 'putri';
        }

        if (preg_match('/\b(putra|pria|laki-laki|cowok)\b/i', $name)) {
            return 'putra';
        }

        return 'campur';
    }

    /** Buang kata generik (kost/kos/tipe a/murah/eksklusif/nama kota) supaya perbandingan fokus ke nama unik kos-nya. */
    protected function normalizeName(string $name): string
    {
        $noise = ['kost', 'kos', 'tipe a', 'tipe b', 'tipe c', 'murah', 'eksklusif', 'putra', 'putri',
            'campur', 'tangerang', 'selatan', 'karawaci', 'serpong', 'bsd', 'city', 'lippo', 'village', '-'];
        $clean = mb_strtolower($name);
        $clean = str_replace($noise, ' ', $clean);
        // Tanda baca dibuang supaya "Kos @The Icon" dan "Kost The Icon"
        // jatuh ke jalur "nama identik", bukan ke pencocokan fuzzy.
        $clean = preg_replace('/[^a-z0-9 ]/', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean);
    }

    protected function cleanName(string $name): string
    {
        return trim(preg_replace('/\s*-\s*MamiKos\s*$/i', '', $name));
    }

    /**
     * Beberapa kos yang sama fisiknya kadang terdaftar dobel -- baik lintas
     * platform (mis. Rukita ikut me-listing properti yang juga ada di
     * Mamikos) MAUPUN lintas area (kategori BSD dan Serpong di Rukita
     * ternyata tumpang tindih secara geografis, satu properti fisik bisa
     * masuk kedua kategori). Karena itu perbandingan di sini SENGAJA tidak
     * dibatasi per-area lagi -- deteksi murni dari kombinasi nama sangat
     * mirip DAN koordinat berdekatan (pola sama seperti findBestMatch()).
     *
     * Yang cocok DIGABUNG, bukan dibuang. Infokost mendaftar satu baris per
     * TIPE KAMAR, sehingga 477 listing-nya sebenarnya cuma ~201 gedung --
     * kalau duplikatnya sekadar dibuang seperti sebelumnya, 276 tipe kamar
     * beserta harganya hilang tanpa jejak. Sekarang tiap varian disimpan
     * sebagai kos_room_types, dan harga kos induk memakai yang TERMURAH
     * supaya terbaca sebagai "mulai dari".
     *
     * Fasilitas & peraturan digabung (union) antar varian: keduanya sifatnya
     * atribut gedung, dan varian yang datanya lebih lengkap melengkapi yang
     * lebih miskin alih-alih saling menimpa.
     */
    protected function dedupeByNameAndProximity(array $combined): array
    {
        $kept = [];

        foreach ($combined as $item) {
            $isDuplicate = false;
            foreach ($kept as $k => $existing) {
                if ($this->isSameProperty($existing, $item)) {
                    $kept[$k] = $this->mergeVariant($existing, $item);
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $item['room_types'] = $this->variantOf($item);
                $kept[] = $item;
            }
        }

        return $kept;
    }

    /**
     * Apakah dua listing ini properti FISIK yang sama?
     *
     * Aturannya sengaja ketat, karena salah gabung jauh lebih merusak
     * daripada gagal gabung: kos berbeda yang dilebur akan hilang permanen
     * dari database beserta harga & fasilitasnya, sedangkan yang gagal
     * dilebur cuma muncul dua kali dan masih bisa dibereskan admin.
     *
     * Versi sebelumnya melebur 76 gedung Karawaci jadi 59 -- "Rukita Woody A"
     * dengan "Woody B/C/E" (93% mirip), "Bambi House" dengan "Anna House",
     * "Rukita Bromo 30 & 32" dengan "Bromo 6/10/18". Dua sebabnya diperbaiki
     * di sini:
     *
     *  (a) Nama identik setelah dinormalkan = varian tipe kamar dari gedung
     *      yang sama. Ini jalur utamanya, dan inilah bentuk data Infokost
     *      (satu baris per tipe kamar, nama gedung diulang persis sama).
     *
     *  (b) Nama sangat mirip DITERIMA hanya kalau koordinat KEDUANYA
     *      diketahui dan berdekatan. Dulu haversineKm() mengembalikan 0
     *      untuk koordinat kosong, sehingga "tidak diketahui" terbaca
     *      sebagai "berjarak 0 km" dan syarat kedekatan selalu lolos --
     *      justru untuk listing Infokost yang belum diperkaya.
     *
     * Token pembeda (huruf tunggal atau angka, mis. "A"/"B" atau "6"/"10")
     * dibandingkan terpisah karena similar_text() nyaris buta terhadapnya:
     * selisih satu karakter pada nama panjang tetap dinilai >90%, padahal
     * justru karakter itulah yang membedakan gedungnya.
     */
    protected function isSameProperty(array $a, array $b): bool
    {
        $nameA = $this->normalizeName($a['name']);
        $nameB = $this->normalizeName($b['name']);

        if ($this->unitTokens($nameA) !== $this->unitTokens($nameB)) {
            return false;
        }

        if ($nameA === $nameB) {
            return true;
        }

        $bothHaveCoords = !empty($a['lat']) && !empty($a['lng']) && !empty($b['lat']) && !empty($b['lng']);
        if (!$bothHaveCoords) {
            return false;
        }

        if ($this->hasDistinctWords($nameA, $nameB)) {
            return false;
        }

        similar_text($nameA, $nameB, $percent);
        if ($percent < self::NAME_MATCH_HIGH) {
            return false;
        }

        return $this->haversineKm($a['lat'], $a['lng'], $b['lat'], $b['lng']) <= self::MAX_MATCH_DISTANCE_KM;
    }

    /**
     * Token pembeda unit: apa pun yang mengandung angka (mis. "l16", "b2",
     * "30") atau huruf tunggal (mis. "woody a"). Kode blok/lantai seperti
     * "L16" vs "L2" adalah SATU token huruf+angka, jadi pola yang cuma
     * mencari angka murni atau huruf tunggal akan melewatkannya -- itulah
     * yang dulu melebur "Studento L16" dengan "Studento L2".
     */
    protected function unitTokens(string $normalizedName): array
    {
        preg_match_all('/\b([a-z]*\d+[a-z]*|[a-z])\b/', $normalizedName, $m);
        $tokens = array_unique($m[1]);
        sort($tokens);

        return $tokens;
    }

    /**
     * Kata bermakna (>=4 huruf) yang membedakan dua nama.
     *
     * similar_text() nyaris buta terhadap satu kata pengganti di tengah nama
     * panjang: "rukita agape studento" vs "rukita pine studento" dinilai
     * 87,8% mirip padahal gedungnya jelas berbeda. Perbandingan per-kata
     * menangkap hal yang tidak bisa ditangkap skor kemiripan karakter.
     *
     * Konsekuensinya sebagian duplikat asli jadi lolos dan muncul dua kali
     * -- itu disengaja: duplikat masih bisa digabung admin belakangan,
     * sedangkan kos yang salah dilebur hilang permanen dari database.
     */
    protected function hasDistinctWords(string $nameA, string $nameB): bool
    {
        $words = static function (string $name): array {
            return array_filter(explode(' ', $name), fn ($w) => mb_strlen($w) >= 4);
        };

        $a = $words($nameA);
        $b = $words($nameB);

        return array_diff($a, $b) !== [] || array_diff($b, $a) !== [];
    }

    /** Satu baris listing -> satu entri tipe kamar (kalau memang bernama). */
    protected function variantOf(array $item): array
    {
        if (empty($item['room_type'])) {
            return [];
        }

        return [$item['room_type'] => $item['price']];
    }

    /**
     * Lebur satu varian ke properti yang sudah tersimpan: kumpulkan tipe
     * kamarnya, turunkan harga induk kalau varian ini lebih murah, dan
     * lengkapi atribut yang masih kosong (koordinat/alamat/foto) dari varian
     * yang kebetulan lebih lengkap datanya.
     */
    protected function mergeVariant(array $existing, array $item): array
    {
        $existing['room_types'] = ($existing['room_types'] ?? []) + $this->variantOf($item);

        if (!empty($item['price']) && $item['price'] < $existing['price']) {
            $existing['price'] = $item['price'];
        }

        foreach (['lat', 'lng', 'street_address', 'fallback_image_url', 'image_local', 'place_id', 'rating'] as $field) {
            if (empty($existing[$field]) && !empty($item[$field])) {
                $existing[$field] = $item[$field];
            }
        }

        $existing['facilities'] = array_values(array_unique(array_merge($existing['facilities'] ?? [], $item['facilities'] ?? [])));
        $existing['rules'] = array_values(array_unique(array_merge($existing['rules'] ?? [], $item['rules'] ?? [])));

        return $existing;
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
                    'description' => ($item['street_address'] ? "Alamat: {$item['street_address']}. " : '')
                        . "Data diimpor dari riset {$item['platform']}"
                        . ($item['place_id'] ? ' + Google Places' : '') . ' (' . now()->format('Y-m-d') . ').'
                        . ($item['rating'] ? " Rating Google: {$item['rating']}." : ''),
                    'verified_at' => null, // belum diverifikasi admin secara manual
                ]);
                $ownerIndex++;
                $imported++;

                // Fasilitas: findOrCreate per nama, lalu attach.
                $facilityIds = collect($item['facilities'])
                    ->map(fn ($name) => $this->canonicalFacility($name))
                    ->filter()
                    ->unique()
                    ->map(fn ($name) => Facility::firstOrCreate(['name' => $name])->id);
                $kos->facilities()->sync($facilityIds);

                // Peraturan kos -- cuma terisi untuk platform yang benar-benar
                // mengeksposnya (saat ini Infokost); sisanya sengaja dibiarkan
                // kosong daripada diisi aturan bawaan yang belum tentu benar.
                if (!empty($item['rules'])) {
                    $ruleIds = collect($item['rules'])
                        ->unique()
                        ->map(fn ($name) => Rule::firstOrCreate(['name' => $name])->id);
                    $kos->rules()->sync($ruleIds);
                }

                // Tipe kamar: satu baris per varian yang tadinya dilebur di
                // mergeVariant(). Tanpa ini, perbedaan harga antar tipe kamar
                // (yang justru jadi alasan platform mendaftarnya terpisah)
                // hilang dan cuma menyisakan harga termurah.
                foreach ($item['room_types'] ?? [] as $roomName => $roomPrice) {
                    $kos->roomTypes()->create([
                        'name' => $roomName,
                        'price' => $roomPrice,
                        // Kapasitas per tipe tidak diekspos platform manapun;
                        // 1 dipakai sebagai nilai netral paling jujur, bukan
                        // tebakan yang membesar-besarkan ketersediaan.
                        'total_rooms' => 1,
                    ]);
                }

                // Foto: prioritaskan foto asli Google Places (place_id ada),
                // fallback ke og:image halaman Mamikos.
                $photoPath = $item['place_id']
                    ? $this->downloadGooglePhoto($item['place_id'], $maps)
                    : null;
                // Foto platform sudah diarsipkan lokal oleh
                // research:download-images -- salin dari situ alih-alih
                // menembak CDN mereka lagi untuk berkas yang sama.
                if (!$photoPath && $item['image_local']) {
                    $photoPath = $this->copyFromResearchArchive($item['image_local']);
                }
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
            $roomTypeTotal = collect($combined)->sum(fn ($x) => count($x['room_types'] ?? []));
            $this->info("Selesai: $imported kos diimpor ($roomTypeTotal tipe kamar), $photosDownloaded dapat foto.");
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

    /** Salin foto dari arsip riset (storage/app/research/images/...) ke disk publik. */
    protected function copyFromResearchArchive(string $relativePath): ?string
    {
        $source = storage_path('app' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if (!File::exists($source)) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'kos-images/' . uniqid('rs_') . '.' . $extension;
        Storage::disk('public')->put($filename, File::get($source));

        return $filename;
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
