<?php

namespace App\Console\Commands;

use App\Services\InfokostService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Lengkapi hasil infokost:scrape dengan koordinat, alamat jalan, fasilitas,
 * dan peraturan dari halaman detail tiap listing.
 *
 * Dipisah dari infokost:scrape (tidak digabung jadi satu opsi --detail)
 * karena dua langkah ini punya biaya yang jauh berbeda: scrape halaman area
 * cuma ~25 request untuk 477 listing, sedangkan pengayaan ini 1 request PER
 * listing. Dipisah berarti daftar listing bisa disegarkan murah tanpa
 * memicu ratusan request, dan pengayaan yang putus di tengah bisa
 * dilanjutkan tanpa mengulang dari nol.
 *
 * Aman dijalankan ulang: listing yang koordinatnya sudah terisi dilewati,
 * kecuali --force.
 */
#[Signature('infokost:enrich
    {--area=all : karawaci|bsd|serpong|all}
    {--delay=3 : Jeda detik antar request halaman detail}
    {--limit= : Batasi jumlah listing yang diperkaya (untuk uji coba)}
    {--force : Perkaya ulang listing yang koordinatnya sudah terisi}')]
#[Description('Lengkapi listing Infokost dengan koordinat, alamat, fasilitas & peraturan dari halaman detail.')]
class EnrichInfokost extends Command
{
    protected const AREAS = ['karawaci', 'bsd', 'serpong'];

    public function handle(InfokostService $infokost): int
    {
        $areaOption = $this->option('area');
        $areas = $areaOption === 'all' ? self::AREAS : array_intersect(self::AREAS, [$areaOption]);

        if (empty($areas)) {
            $this->error('Area tidak dikenal. Pilihan: ' . implode('|', self::AREAS) . '|all.');
            return self::FAILURE;
        }

        $delay = max(1, (int) $this->option('delay'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $force = (bool) $this->option('force');
        $dir = storage_path('app/research');
        $processed = 0;

        foreach ($areas as $area) {
            $file = $dir . DIRECTORY_SEPARATOR . "infokost-{$area}.json";
            if (!File::exists($file)) {
                $this->warn("Lewati '$area': $file tidak ada, jalankan infokost:scrape dulu.");
                continue;
            }

            $listings = json_decode(File::get($file), true) ?? [];
            $enriched = 0;
            $skipped = 0;

            foreach ($listings as $i => $item) {
                if ($limit !== null && $processed >= $limit) {
                    // Simpan dulu sebelum keluar -- "break 2" melompati
                    // penyimpanan di akhir loop area, jadi tanpa baris ini
                    // seluruh hasil pengayaan pada jalannya --limit hilang.
                    $this->writeJson($file, $listings);
                    break 2;
                }

                if (!$force && !empty($item['lat'])) {
                    $skipped++;
                    continue;
                }

                $listings[$i] = $infokost->enrichFromDetailPage($item);
                $processed++;

                if (!empty($listings[$i]['lat'])) {
                    $enriched++;
                    $this->line(sprintf(
                        '  [%s %d/%d] %s -- %d fasilitas, %d peraturan',
                        $area,
                        $i + 1,
                        count($listings),
                        $listings[$i]['name'],
                        count($listings[$i]['facilities'] ?? []),
                        count($listings[$i]['rules'] ?? [])
                    ));
                } else {
                    $this->warn('  [' . $area . ' ' . ($i + 1) . '] gagal: ' . $item['name']);
                }

                // Simpan tiap 10 listing supaya proses yang putus di tengah
                // (jaringan mati, Ctrl+C) tidak kehilangan semua kemajuannya.
                if ($processed % 10 === 0) {
                    $this->writeJson($file, $listings);
                }

                sleep($delay);
            }

            $this->writeJson($file, $listings);
            $withCoords = collect($listings)->whereNotNull('lat')->count();
            $this->info("$area: $enriched diperkaya, $skipped dilewati, total berkoordinat $withCoords/" . count($listings));
        }

        return self::SUCCESS;
    }

    protected function writeJson(string $file, array $listings): void
    {
        File::put($file, json_encode($listings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
