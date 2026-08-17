<?php

namespace App\Console\Commands;

use App\Services\MamikosService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Ambil detail harga/fasilitas/gender/koordinat dari daftar URL kamar
 * Mamikos (satu URL per baris di storage/app/research/mamikos-urls-{area}.txt
 * -- daftar ini dikumpulkan manual lewat pencarian web, BUKAN di-generate
 * command ini). Hasilnya disimpan sebagai JSON referensi, sejajar dengan
 * hasil `places:search-lodging`, untuk nanti digabung lewat
 * `places:merge-sources`.
 */
#[Signature('mamikos:scrape {--area= : karawaci|bsd|serpong (nama berkas mamikos-urls-{area}.txt)} {--delay=2 : Jeda detik antar request}')]
#[Description('Scrape halaman detail kamar Mamikos dari daftar URL, simpan sebagai JSON referensi.')]
class ScrapeMamikos extends Command
{
    public function handle(MamikosService $mamikos): int
    {
        $area = $this->option('area');
        if (!$area) {
            $this->error('Wajib isi --area=karawaci|bsd|serpong.');
            return self::FAILURE;
        }

        $dir = storage_path('app/research');
        $urlFile = $dir . DIRECTORY_SEPARATOR . "mamikos-urls-{$area}.txt";

        if (!File::exists($urlFile)) {
            $this->error("Berkas $urlFile tidak ditemukan.");
            return self::FAILURE;
        }

        $urls = collect(explode("\n", File::get($urlFile)))
            ->map(fn ($u) => trim($u))
            ->filter()
            ->unique()
            ->values();

        $this->info("Scraping {$urls->count()} URL untuk area '$area'...");

        $results = [];
        $failed = 0;
        $delay = (int) $this->option('delay');

        foreach ($urls as $i => $url) {
            $this->line('  [' . ($i + 1) . '/' . $urls->count() . "] $url");

            try {
                $data = $mamikos->fetchListing($url);
            } catch (\Throwable $e) {
                $data = null;
                $this->warn('    error: ' . $e->getMessage());
            }

            // Listing yang sudah tidak aktif dibalas Mamikos dengan halaman generik
            // (harga Rp0, 0 fasilitas, judul homepage) -- itu bukan data asli,
            // dianggap gagal supaya tidak ikut masuk seolah-olah kos gratis beneran.
            $isGenericFallback = $data && (empty($data['price_monthly']) && empty($data['facilities']));

            if ($data && $data['name'] && !$isGenericFallback) {
                $results[] = $data;
                $this->line("    -> {$data['name']} | Rp" . number_format($data['price_monthly'] ?? 0, 0, ',', '.')
                    . " | {$data['gender']} | " . count($data['facilities']) . ' fasilitas');
            } else {
                $failed++;
                $this->warn($isGenericFallback ?? false
                    ? '    -> listing sudah tidak aktif (halaman generik), dilewati'
                    : '    -> gagal / kosong, dilewati');
            }

            if ($i < $urls->count() - 1) {
                sleep($delay);
            }
        }

        $outFile = $dir . DIRECTORY_SEPARATOR . "mamikos-{$area}.json";
        File::put($outFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info(count($results) . " listing berhasil diambil ($failed gagal) -> $outFile");

        return self::SUCCESS;
    }
}
