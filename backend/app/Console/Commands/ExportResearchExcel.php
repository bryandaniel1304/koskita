<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor hasil web scraping jadi SATU berkas .xlsx per platform, dengan
 * satu lembar per area + lembar Ringkasan -- pendamping `research:export-csv`
 * (CSV tetap dipertahankan karena sudah dirujuk sebagai bukti metodologi;
 * .xlsx ditambahkan supaya tautan foto bisa diklik, kolom rapi, dan
 * pembandingan antar-area bisa dilakukan dalam satu berkas).
 *
 * Kolomnya sengaja gabungan dari semua platform, dikosongkan kalau sumber
 * tertentu memang tidak menyediakannya (mis. Infokost tidak memuat
 * koordinat & fasilitas di kartu listing, Mamikos/Rukita tidak memuat
 * jarak ke kampus) -- lebih jujur daripada mengarang nilai pengganti,
 * dan sel kosongnya sendiri jadi informasi soal cakupan tiap sumber.
 *
 * Foto TIDAK ditempel ke dalam sel: berkas ini bisa dilampirkan/dibagikan,
 * sedangkan foto listing berhak cipta milik pemilik kos/platform. Yang
 * dicantumkan adalah tautan aslinya + path arsip lokal hasil
 * `research:download-images`.
 */
#[Signature('research:export-excel {--platform=all : mamikos|rukita|infokost|all}')]
#[Description('Ekspor hasil scraping jadi .xlsx per platform (satu lembar per area + ringkasan).')]
class ExportResearchExcel extends Command
{
    protected const PLATFORMS = [
        'mamikos' => 'Mamikos',
        'rukita' => 'Rukita',
        'infokost' => 'Infokost',
    ];

    protected const AREAS = [
        'karawaci' => 'Karawaci',
        'bsd' => 'BSD',
        'serpong' => 'Gading Serpong',
    ];

    protected const HEADINGS = [
        'No', 'Nama Kos', 'Tipe Kamar', 'Harga/Bulan (Rp)', 'Gender', 'Alamat',
        'Jarak ke Kampus', 'Latitude', 'Longitude', 'Jumlah Fasilitas',
        'Daftar Fasilitas', 'URL Foto', 'Arsip Foto Lokal', 'URL Sumber',
    ];

    public function handle(): int
    {
        $platform = $this->option('platform');
        $platforms = $platform === 'all' ? array_keys(self::PLATFORMS) : [$platform];

        if (array_diff($platforms, array_keys(self::PLATFORMS))) {
            $this->error('Platform tidak dikenal. Pilih: ' . implode('|', array_keys(self::PLATFORMS)) . '|all.');
            return self::FAILURE;
        }

        $dir = storage_path('app/research');
        $outDir = $dir . DIRECTORY_SEPARATOR . 'excel';
        File::ensureDirectoryExists($outDir);

        foreach ($platforms as $key) {
            $perArea = [];

            foreach (array_keys(self::AREAS) as $area) {
                $file = $dir . DIRECTORY_SEPARATOR . "{$key}-{$area}.json";
                $perArea[$area] = File::exists($file)
                    ? (json_decode(File::get($file), true) ?? [])
                    : [];
            }

            if (!array_filter($perArea)) {
                $this->warn(self::PLATFORMS[$key] . ': tidak ada data, dilewati.');
                continue;
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $this->buildSummarySheet($spreadsheet, $key, $perArea);

            foreach ($perArea as $area => $listings) {
                if (empty($listings)) {
                    continue;
                }
                $this->buildAreaSheet($spreadsheet, self::AREAS[$area], $listings);
            }

            $spreadsheet->setActiveSheetIndex(0);

            $outFile = $outDir . DIRECTORY_SEPARATOR . "hasil-scraping-{$key}.xlsx";
            (new Xlsx($spreadsheet))->save($outFile);
            $spreadsheet->disconnectWorksheets();

            $total = array_sum(array_map('count', $perArea));
            $this->info(self::PLATFORMS[$key] . ": $total listing -> $outFile");
        }

        return self::SUCCESS;
    }

    /** Lembar pertama: berapa listing per area & seberapa lengkap datanya. */
    protected function buildSummarySheet(Spreadsheet $spreadsheet, string $platformKey, array $perArea): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');

        $sheet->setCellValue('A1', 'Hasil Web Scraping -- ' . self::PLATFORMS[$platformKey]);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Diekspor: ' . now()->format('d M Y H:i'));
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $headings = ['Area', 'Jumlah Listing', 'Ada Harga', 'Ada Foto', 'Ada Koordinat', 'Ada Jarak Kampus'];
        $sheet->fromArray($headings, null, 'A4');
        $this->styleHeaderRow($sheet, 4, count($headings));

        $row = 5;
        foreach ($perArea as $area => $listings) {
            $sheet->fromArray([
                self::AREAS[$area],
                count($listings),
                $this->countFilled($listings, 'price_monthly'),
                $this->countFilled($listings, 'image_url'),
                $this->countFilled($listings, 'lat'),
                $this->countFilled($listings, 'distance_minutes'),
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, array_sum(array_map('count', $perArea)));
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);

        $sheet->setCellValue('A' . ($row + 2), 'Catatan: sel kosong berarti platform ini memang tidak menyediakan atribut tersebut, bukan gagal diambil.');
        $sheet->getStyle('A' . ($row + 2))->getFont()->setItalic(true)->setSize(9);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function buildAreaSheet(Spreadsheet $spreadsheet, string $title, array $listings): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $sheet->fromArray(self::HEADINGS, null, 'A1');
        $this->styleHeaderRow($sheet, 1, count(self::HEADINGS));

        $row = 2;
        foreach ($listings as $i => $item) {
            $facilities = $item['facilities'] ?? [];

            $sheet->fromArray([
                $i + 1,
                $item['name'] ?? '',
                $item['room_type'] ?? '',
                $item['price_monthly'] ?? null,
                $item['gender'] ?? '',
                $item['address'] ?? '',
                $item['distance_text'] ?? '',
                $item['lat'] ?? null,
                $item['lng'] ?? null,
                count($facilities),
                implode('; ', $facilities),
                $item['image_url'] ?? '',
                $item['image_local'] ?? '',
                $item['source_url'] ?? '',
            ], null, 'A' . $row);

            // Tautan foto & sumber dibikin bisa diklik -- gunanya berkas ini
            // untuk verifikasi: pembimbing bisa langsung membuka listing
            // aslinya buat mengecek datanya benar.
            $this->linkify($sheet, 'L' . $row, $item['image_url'] ?? null);
            $this->linkify($sheet, 'N' . $row, $item['source_url'] ?? null);

            $row++;
        }

        $lastRow = $row - 1;
        $sheet->getStyle('D2:D' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setAutoFilter('A1:N' . $lastRow);
        $sheet->freezePane('A2');

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // Kolom teks panjang: lebar dikunci supaya autosize tidak bikin
        // satu kolom selebar layar.
        foreach (['F' => 32, 'G' => 28, 'K' => 30, 'L' => 40, 'M' => 34, 'N' => 44] as $col => $width) {
            $sheet->getColumnDimension($col)->setAutoSize(false);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    protected function styleHeaderRow(Worksheet $sheet, int $row, int $columnCount): void
    {
        $lastColumn = chr(ord('A') + $columnCount - 1);
        $range = "A{$row}:{$lastColumn}{$row}";

        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF355DDB');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    protected function linkify(Worksheet $sheet, string $cell, ?string $url): void
    {
        if (!$url || !str_starts_with($url, 'http')) {
            return;
        }

        $sheet->getCell($cell)->getHyperlink()->setUrl($url);
        $sheet->getStyle($cell)->getFont()->setUnderline(true)->getColor()->setARGB('FF0563C1');
    }

    protected function countFilled(array $listings, string $field): int
    {
        return count(array_filter($listings, fn ($item) => !empty($item[$field])));
    }
}
