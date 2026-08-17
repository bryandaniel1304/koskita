<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Saring noise dari hasil mentah `places:search-lodging` sebelum ditinjau
 * manual. Google tidak punya kategori "kos" khusus, jadi kata kunci teks
 * kita ikut menjangkau tempat yang jelas bukan akomodasi (cafe, gym,
 * universitas, dst.) maupun akomodasi formal (hotel/hostel/guesthouse)
 * yang secara definisi beda dari kos untuk mahasiswa/pekerja.
 *
 * Dua tahap penyaringan:
 * 1. Buang yang tidak punya tipe "lodging" sama sekali -- ini noise murni
 *    (cafe, gym, spa, universitas, dll ikut nyangkut karena query teks).
 * 2. Beri tanda "likely_kos" (bukan buang) berdasarkan nama mengandung
 *    kata kos/kost/kosan, supaya hotel/hostel/guesthouse formal tetap ada
 *    di berkas (untuk pembanding pada Tabel 3.1) tapi mudah dibedakan saat
 *    ditinjau manual, bukan malah hilang diam-diam.
 */
#[Signature('places:filter-lodging {--area=all : karawaci|bsd|serpong|all}')]
#[Description('Saring noise dari hasil places:search-lodging dan tandai kandidat kos vs akomodasi formal lainnya.')]
class FilterLodging extends Command
{
    protected const AREAS = ['karawaci', 'bsd', 'serpong'];

    // Kata kunci yang menandakan nama tempat memang kos/kosan untuk
    // mahasiswa/pekerja, bukan hotel/hostel formal.
    protected const KOS_NAME_HINTS = ['kos', 'kost', 'kosan', 'kostan'];

    // Tipe Places API yang menandakan akomodasi formal (bukan kos rumahan)
    // -- dipakai untuk kolom "category" pada hasil, bukan untuk membuang.
    protected const FORMAL_TYPES = ['hotel', 'motel', 'inn', 'hostel', 'extended_stay_hotel', 'resort_hotel'];

    public function handle(): int
    {
        $areaOption = $this->option('area');
        $areas = $areaOption === 'all' ? self::AREAS : array_intersect(self::AREAS, [$areaOption]);

        if (empty($areas)) {
            $this->error("Area '$areaOption' tidak dikenal. Pilihan: " . implode(', ', self::AREAS) . ', all.');
            return self::FAILURE;
        }

        $dir = storage_path('app/research');
        $totalBefore = 0;
        $totalAfter = 0;
        $totalKos = 0;

        foreach ($areas as $area) {
            $matches = File::glob($dir . DIRECTORY_SEPARATOR . "lodging-{$area}-*.json");
            $rawFile = end($matches); // ambil yang paling baru kalau ada lebih dari satu tanggal

            if (!$rawFile) {
                $this->warn("Tidak ada berkas mentah untuk area '$area' -- jalankan places:search-lodging dulu.");
                continue;
            }

            $data = json_decode(File::get($rawFile), true) ?? [];
            $before = count($data);

            $filtered = collect($data)
                ->filter(fn ($place) => in_array('lodging', $place['types'] ?? [], true))
                ->map(function ($place) {
                    $name = mb_strtolower($place['name'] ?? '');
                    $isKosByName = collect(self::KOS_NAME_HINTS)->contains(fn ($hint) => str_contains($name, $hint));
                    $isFormalType = !empty(array_intersect($place['types'] ?? [], self::FORMAL_TYPES));

                    $place['likely_kos'] = $isKosByName && !$isFormalType;
                    $place['category'] = $place['likely_kos']
                        ? 'kos'
                        : ($isFormalType ? 'akomodasi_formal' : 'perlu_ditinjau_manual');

                    return $place;
                })
                ->values();

            $after = $filtered->count();
            $kosCount = $filtered->where('likely_kos', true)->count();

            $outFile = $dir . DIRECTORY_SEPARATOR . "lodging-{$area}-filtered.json";
            File::put($outFile, json_encode($filtered->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info(ucfirst($area) . ": $before -> $after tempat (noise dibuang: " . ($before - $after) . "), "
                . "$kosCount ditandai likely_kos=true -> $outFile");

            $totalBefore += $before;
            $totalAfter += $after;
            $totalKos += $kosCount;
        }

        $this->newLine();
        $this->comment("Total: $totalBefore -> $totalAfter tempat, $totalKos ditandai kandidat kos kuat.");
        $this->comment('"category"=perlu_ditinjau_manual berarti bukan tipe hotel formal TAPI namanya juga');
        $this->comment('tidak mengandung kata kos/kost -- masih perlu dicek manual satu per satu (mis. rumah');
        $this->comment('kontrakan, apartemen, dsb yang ikut tertangkap tipe "lodging").');

        return self::SUCCESS;
    }
}
