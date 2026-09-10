<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Unduh foto listing hasil scraping ke storage/app/research/images/{platform}/
 * untuk keperluan ANALISIS PRIBADI (mis. memeriksa kualitas foto sebagai
 * atribut, atau ilustrasi metodologi di buku skripsi).
 *
 * Foto listing adalah ciptaan berhak cipta milik pemilik kos/platform --
 * berkas hasil unduhan ini TIDAK untuk didistribusikan ulang atau
 * ditayangkan di aplikasi KosKita. Karena itu berkasnya sengaja ditaruh di
 * storage/app (bukan storage/app/public yang tersaji lewat web) dan
 * Excel-nya hanya menyimpan TAUTAN + path lokal, bukan menempelkan
 * gambarnya ke dalam berkas yang disebar.
 *
 * Path lokal ditulis balik ke JSON sumber sebagai `image_local` supaya
 * `research:export-excel` bisa mencantumkannya tanpa menebak nama berkas.
 */
#[Signature('research:download-images
    {--platform=all : mamikos|rukita|infokost|all}
    {--delay=1 : Jeda detik antar unduhan}
    {--limit= : Batasi jumlah unduhan per platform (untuk uji coba)}')]
#[Description('Unduh foto listing hasil scraping ke storage/app/research/images/{platform}/ (arsip riset pribadi).')]
class DownloadResearchImages extends Command
{
    protected const PLATFORMS = ['mamikos', 'rukita', 'infokost'];

    protected const AREAS = ['karawaci', 'bsd', 'serpong'];

    protected const USER_AGENT = 'KosKita-Skripsi-Research/1.0 (+riset tugas akhir, bukan bot komersial)';

    public function handle(): int
    {
        $platform = $this->option('platform');
        $platforms = $platform === 'all' ? self::PLATFORMS : [$platform];

        if (array_diff($platforms, self::PLATFORMS)) {
            $this->error('Platform tidak dikenal. Pilih: ' . implode('|', self::PLATFORMS) . '|all.');
            return self::FAILURE;
        }

        $delay = (int) $this->option('delay');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dir = storage_path('app/research');

        foreach ($platforms as $key) {
            $imageDir = $dir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $key;
            File::ensureDirectoryExists($imageDir);

            $downloaded = 0;
            $skipped = 0;
            $failed = 0;

            foreach (self::AREAS as $area) {
                $file = $dir . DIRECTORY_SEPARATOR . "{$key}-{$area}.json";
                if (!File::exists($file)) {
                    continue;
                }

                $listings = json_decode(File::get($file), true) ?? [];
                $changed = false;

                foreach ($listings as $index => $item) {
                    if ($limit !== null && $downloaded >= $limit) {
                        break 2;
                    }

                    $url = $item['image_url'] ?? null;
                    if (!$url) {
                        continue;
                    }

                    $filename = $this->filenameFor($key, $area, $index, $item, $url);
                    $path = $imageDir . DIRECTORY_SEPARATOR . $filename;
                    $relative = "research/images/{$key}/{$filename}";

                    // Sudah pernah diunduh -- lewati, supaya command ini aman
                    // dijalankan ulang tanpa mengunduh ratusan berkas lagi.
                    if (File::exists($path)) {
                        if (($listings[$index]['image_local'] ?? null) !== $relative) {
                            $listings[$index]['image_local'] = $relative;
                            $changed = true;
                        }
                        $skipped++;
                        continue;
                    }

                    try {
                        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($url);

                        if (!$response->successful()) {
                            $failed++;
                            continue;
                        }

                        File::put($path, $response->body());
                        $listings[$index]['image_local'] = $relative;
                        $changed = true;
                        $downloaded++;
                    } catch (\Throwable $e) {
                        $this->warn("    gagal: $url -- " . $e->getMessage());
                        $failed++;
                        continue;
                    }

                    if ($delay > 0) {
                        sleep($delay);
                    }
                }

                if ($changed) {
                    File::put($file, json_encode($listings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }

            $this->info("$key: $downloaded diunduh, $skipped sudah ada, $failed gagal -> $imageDir");
        }

        return self::SUCCESS;
    }

    /**
     * Nama berkas yang stabil & bisa ditelusuri balik ke listing-nya:
     * {area}-{nomor}-{nama-kos}.{ext}. Nomor urut dipakai supaya dua kamar
     * dari gedung yang sama tidak saling menimpa berkas.
     */
    protected function filenameFor(string $platform, string $area, int $index, array $item, string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $name = Str::slug(Str::limit($item['name'] ?? 'tanpa-nama', 50, ''));

        return sprintf('%s-%03d-%s.%s', $area, $index + 1, $name ?: 'tanpa-nama', $ext);
    }
}
