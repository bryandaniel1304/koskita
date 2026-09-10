<?php

namespace App\Console\Commands;

use App\Services\InfokostService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Scrape listing Infokost per area, disimpan sejajar dengan
 * mamikos-{area}.json & rukita-{area}.json di storage/app/research/
 * supaya ikut terbaca `research:export-csv` dan `places:import-koses`.
 *
 * Satu area KosKita dipetakan ke BEBERAPA slug kecamatan Infokost --
 * "BSD" misalnya tidak punya halaman sendiri di sana karena BSD City
 * secara administratif tersebar di Pagedangan & Cisauk.
 *
 * --pages dibatasi (default 5, bukan seluruh 29 halaman yang tersedia
 * untuk Karawaci) supaya beban ke server mereka wajar; naikkan manual
 * kalau memang butuh sampel lebih besar.
 */
#[Signature('infokost:scrape
    {--area= : karawaci|bsd|serpong}
    {--pages=5 : Jumlah halaman per slug area (1 halaman = ~21 listing)}
    {--delay=3 : Jeda detik antar request}')]
#[Description('Scrape listing Infokost per area, simpan sebagai JSON referensi di storage/app/research/.')]
class ScrapeInfokost extends Command
{
    /** Satu area KosKita -> slug kecamatan Infokost yang mencakupinya. */
    protected const AREA_SLUGS = [
        'karawaci' => ['karawaci-tangerang', 'kelapa-dua-tangerang'],
        'bsd' => ['pagedangan-tangerang', 'cisauk-tangerang'],
        'serpong' => ['serpong-tangerang-selatan', 'serpong-utara-tangerang-selatan'],
    ];

    public function handle(InfokostService $infokost): int
    {
        $area = $this->option('area');

        if (!$area || !isset(self::AREA_SLUGS[$area])) {
            $this->error('Wajib isi --area=karawaci|bsd|serpong.');
            return self::FAILURE;
        }

        $maxPages = max(1, (int) $this->option('pages'));
        $delay = max(1, (int) $this->option('delay'));

        $all = [];
        $seen = [];

        foreach (self::AREA_SLUGS[$area] as $slug) {
            $available = $infokost->detectTotalPages($slug);
            $pages = min($maxPages, $available);
            $this->info("Slug '$slug': $available halaman tersedia, mengambil $pages.");
            sleep($delay);

            for ($page = 1; $page <= $pages; $page++) {
                $items = $infokost->fetchAreaPage($slug, $page);
                $this->line("  [$slug hal. $page/$pages] " . count($items) . ' listing');

                foreach ($items as $item) {
                    // Satu kos bisa muncul di dua slug yang bertetangga --
                    // dedup pakai URL listing supaya tidak dihitung ganda.
                    if (isset($seen[$item['source_url']])) {
                        continue;
                    }
                    $seen[$item['source_url']] = true;
                    $item['area'] = $area;
                    $item['source_platform'] = 'infokost';
                    $all[] = $item;
                }

                if ($page < $pages) {
                    sleep($delay);
                }
            }
        }

        if (empty($all)) {
            $this->warn('Tidak ada hasil -- kemungkinan struktur halaman berubah, cek InfokostService::parseCards.');
            return self::FAILURE;
        }

        $dir = storage_path('app/research');
        File::ensureDirectoryExists($dir);
        $outFile = $dir . DIRECTORY_SEPARATOR . "infokost-{$area}.json";
        File::put($outFile, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $withPhoto = collect($all)->whereNotNull('image_url')->count();
        $this->info(count($all) . " listing unik disimpan -> $outFile");
        $this->info("Punya foto: $withPhoto | Punya data jarak kampus: " . collect($all)->whereNotNull('distance_minutes')->count());

        return self::SUCCESS;
    }
}
