<?php

namespace App\Console\Commands;

use App\Services\RukitaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Scrape listing Rukita per area (satu request kategori -> puluhan properti
 * sekaligus, lalu tiap properti dilengkapi foto/koordinat/fasilitas dari
 * halaman detailnya). Hasilnya disimpan sejajar dengan mamikos-{area}.json
 * di storage/app/research/, untuk digabung lewat places:import-koses.
 */
#[Signature('rukita:scrape {--area= : karawaci|bsd|serpong} {--delay=2 : Jeda detik antar request halaman detail}')]
#[Description('Scrape listing Rukita per area (kategori + detail), simpan sebagai JSON referensi.')]
class ScrapeRukita extends Command
{
    protected const AREA_SLUGS = [
        'karawaci' => 'karawaci-tangerang',
        'bsd' => 'bsd-tangerang',
        'serpong' => 'gading-serpong-tangerang',
    ];

    public function handle(RukitaService $rukita): int
    {
        $area = $this->option('area');
        if (!$area || !isset(self::AREA_SLUGS[$area])) {
            $this->error('Wajib isi --area=karawaci|bsd|serpong.');
            return self::FAILURE;
        }

        $slug = self::AREA_SLUGS[$area];
        $delay = (int) $this->option('delay');

        $this->info("Mengambil daftar properti Rukita area '$area' (slug: $slug)...");
        $listing = $rukita->fetchCategoryListing($slug);
        $this->info(count($listing) . ' properti ditemukan di halaman kategori.');

        if (empty($listing)) {
            $this->warn('Tidak ada hasil -- cek slug area atau struktur halaman berubah.');
            return self::FAILURE;
        }

        $results = [];
        foreach ($listing as $i => $item) {
            $this->line('  [' . ($i + 1) . '/' . count($listing) . "] {$item['name']} ({$item['price_text']})");

            if (!$item['price_monthly']) {
                $this->warn('    -> harga tidak terbaca, dilewati');
                continue;
            }

            try {
                $enriched = $rukita->enrichFromDetailPage($item);
            } catch (\Throwable $e) {
                $this->warn('    -> error saat ambil detail: ' . $e->getMessage());
                continue;
            }

            $results[] = $enriched;
            sleep($delay);
        }

        $dir = storage_path('app/research');
        File::ensureDirectoryExists($dir);
        $outFile = $dir . DIRECTORY_SEPARATOR . "rukita-{$area}.json";
        File::put($outFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info(count($results) . " listing berhasil diambil -> $outFile");

        return self::SUCCESS;
    }
}
