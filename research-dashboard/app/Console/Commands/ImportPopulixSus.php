<?php

namespace App\Console\Commands;

use App\Models\PopulixSusResponse;
use App\Models\SusResponse;
use App\Support\XlsxReader;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Impor rekap jawaban SUS dari panel Populix (.xlsx atau .csv) ke tabel
 * populix_sus_responses. Kolom wajib: "Id Populix" dan "Q1" s.d. "Q10";
 * kolom "Gender", "Usia", "Provinsi", "Kota", "Waktu Selesai", dan
 * "Skor SUS" ikut dibaca bila ada. Idempoten: baris dengan Id Populix yang
 * sama diperbarui, bukan diduplikasi. Bila satu baris saja tidak valid,
 * tidak ada yang disimpan.
 */
#[Signature('sus:import-populix
    {file? : Berkas .xlsx/.csv (bawaan: ../_hasil-sus/Analisis_SUS_Populix_100.xlsx)}
    {--sheet=Skor SUS : Nama sheet untuk berkas .xlsx}')]
#[Description('Impor jawaban kuesioner SUS dari panel Populix ke dashboard penelitian.')]
class ImportPopulixSus extends Command
{
    public function handle(): int
    {
        $path = $this->argument('file') ?: base_path('../_hasil-sus/Analisis_SUS_Populix_100.xlsx');
        if (!is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        try {
            $rows = str_ends_with(strtolower($path), '.csv')
                ? $this->csvRows($path)
                : XlsxReader::rows($path, (string) $this->option('sheet'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $records = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2; // baris 1 = judul kolom
            $result = $this->toRecord($row);
            if (is_string($result)) {
                $errors[] = "Baris {$line}: {$result}";
            } elseif (isset($records[$result['populix_id']])) {
                $errors[] = "Baris {$line}: Id Populix {$result['populix_id']} muncul lebih dari sekali.";
            } else {
                $records[$result['populix_id']] = $result;
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Impor dibatalkan; tidak ada data yang disimpan.');
            return self::FAILURE;
        }

        if ($records === []) {
            $this->warn('Tidak ada baris jawaban di berkas ini.');
            return self::SUCCESS;
        }

        $existing = PopulixSusResponse::whereIn('populix_id', array_keys($records))->count();
        DB::transaction(function () use ($records) {
            foreach ($records as $record) {
                PopulixSusResponse::updateOrCreate(['populix_id' => $record['populix_id']], $record);
            }
        });

        $count = count($records);
        $this->info("{$count} jawaban Populix diimpor (" . ($count - $existing) . " baru, {$existing} diperbarui).");
        $this->line('Total di dashboard: ' . PopulixSusResponse::count() . ' jawaban Populix.');

        return self::SUCCESS;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>|string record siap simpan, atau pesan galat
     */
    private function toRecord(array $row): array|string
    {
        $populixId = trim((string) ($row['Id Populix'] ?? ''));
        if ($populixId === '') {
            return 'kolom "Id Populix" kosong.';
        }

        $answers = [];
        foreach (range(1, 10) as $n) {
            $raw = trim((string) ($row["Q$n"] ?? ''));
            if (!preg_match('/^[1-5](\.0+)?$/', $raw)) {
                return "Q{$n} harus angka 1-5, terbaca \"{$raw}\".";
            }
            $answers[] = (int) $raw;
        }

        // Skor selalu dihitung ulang dengan rumus Brooke; bila berkas membawa
        // skor sendiri, keduanya wajib sama supaya salah ketik ketahuan.
        $score = SusResponse::calculateScore($answers);
        $fileScore = trim((string) ($row['Skor SUS'] ?? ''));
        if ($fileScore !== '' && abs((float) str_replace(',', '.', $fileScore) - $score) > 0.001) {
            return "Skor SUS di berkas ({$fileScore}) berbeda dengan hitungan rumus Brooke ({$score}).";
        }

        $completedRaw = trim((string) ($row['Waktu Selesai'] ?? ''));
        try {
            $completedAt = $this->parseTimestamp($completedRaw);
        } catch (Throwable) {
            return "Waktu Selesai \"{$completedRaw}\" tidak dikenali.";
        }

        $age = trim((string) ($row['Usia'] ?? ''));

        return [
            'populix_id' => $populixId,
            'gender' => trim((string) ($row['Gender'] ?? '')) ?: null,
            'age' => ctype_digit($age) ? (int) $age : null,
            'province' => trim((string) ($row['Provinsi'] ?? '')) ?: null,
            'city' => trim((string) ($row['Kota'] ?? '')) ?: null,
            'completed_at' => $completedAt,
            'sus_score' => $score,
        ] + array_combine(array_map(fn ($n) => "q$n", range(1, 10)), $answers);
    }

    /** "29/10/2026 17:58:50" (format ekspor Populix) atau angka tanggal Excel. */
    private function parseTimestamp(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addSeconds((int) round((float) $value * 86400));
        }

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $value);
        } catch (Throwable) {
            return Carbon::parse($value);
        }
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $headers = array_map(fn ($h) => trim($h, " \t\n\r\0\x0B\u{FEFF}"), str_getcsv($first, $delimiter));

        $rows = [];
        while (($cells = fgetcsv($handle, null, $delimiter)) !== false) {
            if (array_filter($cells, fn ($v) => trim((string) $v) !== '') === []) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), ''));
        }
        fclose($handle);

        return $rows;
    }
}
