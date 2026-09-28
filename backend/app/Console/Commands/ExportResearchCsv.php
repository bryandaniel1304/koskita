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

    /** Label area yang dipakai di berkas keluaran -- sama dengan research:export-excel. */
    protected const AREA_LABELS = [
        'karawaci' => 'Karawaci',
        'bsd' => 'BSD',
        'serpong' => 'Gading Serpong',
    ];

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
                    // Kolom & urutannya sengaja dibuat sama persis dengan
                    // research:export-excel supaya kedua berkas bisa
                    // dibandingkan/di-diff baris per baris. Kolom yang cuma
                    // diisi sebagian sumber (jarak kampus dari Infokost,
                    // koordinat dari Mamikos) dikosongkan, bukan dikarang.
                    $rows[] = [
                        'Area' => self::AREA_LABELS[$area] ?? ucfirst($area),
                        'Nama Kos' => $item['name'] ?? '',
                        'Tipe Kamar' => $item['room_type'] ?? '',
                        'Harga/Bulan (Rp)' => $item['price_monthly'] ?? '',
                        'Gender' => $this->genderLabel($item['gender'] ?? ''),
                        'Kecamatan' => $item['address'] ?? '',
                        'Alamat Jalan' => $item['street_address'] ?? '',
                        'Jarak ke Kampus' => $item['distance_text'] ?? '',
                        'Latitude' => $item['lat'] ?? '',
                        'Longitude' => $item['lng'] ?? '',
                        'Jumlah Fasilitas' => count($item['facilities'] ?? []),
                        'Daftar Fasilitas' => implode('; ', $item['facilities'] ?? []),
                        'Jumlah Peraturan' => count($item['rules'] ?? []),
                        'Daftar Peraturan' => implode('; ', $item['rules'] ?? []),
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

    /** Samakan penulisan gender dengan research:export-excel. */
    protected function genderLabel(string $gender): string
    {
        return match ($gender) {
            'putra' => 'Putra',
            'putri' => 'Putri',
            'campur' => 'Campur',
            default => $gender,
        };
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
