<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Ekspor hasil riset web scraping (storage/app/research/{platform}-{area}.json)
 * jadi satu berkas CSV per platform -- dibuka langsung di Excel sebagai bukti
 * metodologi pengumpulan data ke dosen pembimbing (per arahan: "web scraping,
 * hasilnya taro di excel per platform kosan"). CSV dipakai (bukan .xlsx asli)
 * supaya tidak perlu dependency Composer baru -- Excel/Sheets/LibreOffice
 * semua bisa buka CSV langsung.
 */
#[Signature('research:export-csv')]
#[Description('Gabungkan semua hasil scraping per platform (Mamikos/Rukita/dst) jadi satu CSV per platform di storage/app/research/excel/.')]
class ExportResearchCsv extends Command
{
    protected const PLATFORMS = [
        'mamikos' => 'Mamikos',
        'rukita' => 'Rukita',
        'infokost' => 'Infokost',
    ];

    protected const AREAS = ['karawaci', 'bsd', 'serpong'];

    public function handle(): int
    {
        $dir = storage_path('app/research');
        $outDir = $dir . DIRECTORY_SEPARATOR . 'excel';
        File::ensureDirectoryExists($outDir);

        foreach (self::PLATFORMS as $key => $label) {
            $rows = [];

            foreach (self::AREAS as $area) {
                $file = $dir . DIRECTORY_SEPARATOR . "{$key}-{$area}.json";
                if (!File::exists($file)) {
                    continue;
                }

                $listings = json_decode(File::get($file), true) ?? [];
                foreach ($listings as $item) {
                    $rows[] = [
                        'Area' => ucfirst($area),
                        'Nama Kos' => $item['name'] ?? '',
                        // Kolom berikut cuma diisi sebagian sumber (mis. tipe
                        // kamar & jarak kampus dari Infokost, koordinat dari
                        // Mamikos) -- dikosongkan, bukan diisi nilai karangan.
                        'Tipe Kamar' => $item['room_type'] ?? '',
                        'Harga/Bulan (Rp)' => $item['price_monthly'] ?? '',
                        'Tipe Gender' => $item['gender'] ?? '',
                        'Alamat' => $item['address'] ?? '',
                        'Jarak ke Kampus' => $item['distance_text'] ?? '',
                        'Latitude' => $item['lat'] ?? '',
                        'Longitude' => $item['lng'] ?? '',
                        'Jumlah Fasilitas' => count($item['facilities'] ?? []),
                        'Daftar Fasilitas' => implode('; ', $item['facilities'] ?? []),
                        'Ada Foto' => !empty($item['image_url']) ? 'Ya' : 'Tidak',
                        'URL Foto' => $item['image_url'] ?? '',
                        'Arsip Foto Lokal' => $item['image_local'] ?? '',
                        'URL Sumber' => $item['source_url'] ?? '',
                    ];
                }
            }

            if (empty($rows)) {
                $this->warn("$label: tidak ada data, dilewati.");
                continue;
            }

            $outFile = $outDir . DIRECTORY_SEPARATOR . "hasil-scraping-{$key}.csv";
            $this->writeCsv($outFile, $rows);
            $this->info("$label: " . count($rows) . " baris -> $outFile");
        }

        return self::SUCCESS;
    }

    protected function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        // BOM UTF-8 supaya karakter Indonesia (mis. "Rp", huruf biasa) tampil
        // benar saat dibuka langsung di Microsoft Excel, bukan cuma di teks editor.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
