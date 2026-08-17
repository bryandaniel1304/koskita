<?php

namespace App\Console\Commands;

use App\Services\GoogleMapsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Riset data akomodasi (kos, kost, guesthouse, hotel) di sekitar wilayah
 * studi kasus skripsi (Karawaci/BSD/Serpong, sesuai Pembatasan Masalah
 * poin 2) lewat Places API. Hasilnya DITULIS SEBAGAI BERKAS REFERENSI di
 * storage/app/research/ untuk ditinjau manual -- sengaja TIDAK auto-import
 * ke tabel `koses`, karena (a) hasil pencarian "lodging" turut menangkap
 * hotel/guesthouse yang bukan kos, perlu disortir manual dulu, dan
 * (b) Google Maps Platform ToS membatasi penyimpanan permanen hasil
 * Places API -- lihat catatan lengkap di GoogleMapsService.
 */
#[Signature('places:search-lodging {--area=all : karawaci|bsd|serpong|all}')]
#[Description('Cari akomodasi (kos/kost/guesthouse/hotel) di sekitar area studi kasus via Google Places API, simpan sebagai JSON referensi.')]
class SearchLodging extends Command
{
    protected const AREAS = [
        'karawaci' => ['lat' => -6.2280, 'lng' => 106.5985, 'label' => 'Lippo Village Karawaci'],
        'bsd' => ['lat' => -6.3019, 'lng' => 106.6524, 'label' => 'BSD City'],
        'serpong' => ['lat' => -6.2879, 'lng' => 106.6759, 'label' => 'Serpong'],
    ];

    // Beberapa variasi kata kunci supaya kos kecil yang labelnya tidak
    // konsisten (kos/kost/kos-kosan) tetap tertangkap, plus "guest house"
    // untuk akomodasi non-kos yang relevan sebagai pembanding.
    protected const KEYWORDS = ['kos', 'kost', 'guest house', 'kos-kosan putra putri'];

    public function handle(GoogleMapsService $maps): int
    {
        if (!$maps->configured()) {
            $this->error('GOOGLE_MAPS_API_KEY belum diisi di .env.');
            return self::FAILURE;
        }

        $areaOption = $this->option('area');
        $areas = $areaOption === 'all' ? self::AREAS : array_intersect_key(self::AREAS, [$areaOption => true]);

        if (empty($areas)) {
            $this->error("Area '$areaOption' tidak dikenal. Pilihan: " . implode(', ', array_keys(self::AREAS)) . ', all.');
            return self::FAILURE;
        }

        $outputDir = storage_path('app/research');
        File::ensureDirectoryExists($outputDir);

        foreach ($areas as $key => $area) {
            $this->info("Mencari akomodasi di sekitar {$area['label']}...");
            $combined = [];

            foreach (self::KEYWORDS as $keyword) {
                $query = "$keyword di {$area['label']}";
                $this->line("  - query: \"$query\"");
                $results = $maps->searchText($query, $area['lat'], $area['lng']);
                $this->line('    -> ' . count($results) . ' hasil');

                foreach ($results as $r) {
                    if ($r['place_id']) {
                        $combined[$r['place_id']] = $r; // dedupe pakai place_id sebagai key
                    }
                }

                // Jeda antar query supaya tidak membombardir API dalam waktu singkat.
                sleep(1);
            }

            $filename = "lodging-{$key}-" . now()->format('Y-m-d') . '.json';
            $path = $outputDir . DIRECTORY_SEPARATOR . $filename;
            File::put($path, json_encode(array_values($combined), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('  Disimpan: ' . count($combined) . " tempat unik -> $path");
        }

        $this->newLine();
        $this->comment('Hasil ini referensi mentah, bukan data final siap pakai. Tinjau manual');
        $this->comment('satu per satu (pisahkan kos asli dari hotel/guesthouse berdasarkan kolom');
        $this->comment('"types") sebelum dipakai sebagai bahan seed tabel koses.');

        return self::SUCCESS;
    }
}
