<?php

namespace App\Services;

use App\Models\SusResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Membaca file jawaban SUS (xlsx/xls/csv/ods, mis. ekspor Google Form) dan
 * menyimpannya sebagai SusResponse dengan source = "import".
 *
 * Urutan kolom di file bebas: kolom dikenali dari judulnya --
 *  - Nama        : judul memuat "nama" / "name"
 *  - Email       : judul memuat "email" / "surel" (opsional)
 *  - Waktu isi   : "Timestamp" / "Stempel waktu" / "Waktu" / "Tanggal" (opsional)
 *  - Kesan&Saran : judul memuat "kesan" / "saran" / "feedback" / "komentar" (opsional)
 *  - Q1..Q10     : judul "Q1".."Q10", "1. ...", atau teks pernyataan SUS-nya
 * Jawaban boleh angka 1-5 atau label skala Likert ("Sangat Setuju", dst.).
 *
 * Semua-atau-tidak-sama-sekali: kalau ada satu baris yang tidak valid, tidak
 * ada yang disimpan, supaya file bisa diperbaiki lalu diunggah ulang tanpa
 * meninggalkan data setengah masuk.
 */
class SusImporter
{
    /** Urutan penting: label yang lebih panjang dicek lebih dulu. */
    private const LIKERT = [
        'sangat tidak setuju' => 1,
        'sangat setuju' => 5,
        'tidak setuju' => 2,
        'ragu-ragu' => 3,
        'ragu ragu' => 3,
        'netral' => 3,
        'setuju' => 4,
    ];

    /**
     * @return array{imported:int, skipped:int, errors:array<int,string>, columns:array<string,string>}
     */
    public function import(string $path, ?string $originalName = null): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, false);
        } catch (Throwable $e) {
            return $this->fail('File tidak bisa dibaca. Gunakan format .xlsx, .xls, .csv, atau .ods.');
        }

        [$headerIndex, $map] = $this->findHeader($rows);
        if ($headerIndex === null) {
            return $this->fail('Kolom tidak dikenali. Pastikan baris judul memuat kolom Nama dan Q1 sampai Q10 (atau teks pernyataan SUS).');
        }

        $missing = array_diff(array_merge(['name'], array_map(fn ($n) => "q$n", range(1, 10))), array_keys($map));
        if ($missing) {
            $labels = array_map(fn ($k) => $k === 'name' ? 'Nama' : strtoupper($k), $missing);

            return $this->fail('Kolom berikut tidak ditemukan: ' . implode(', ', $labels) . '.', $this->describeColumns($rows[$headerIndex], $map));
        }

        $records = [];
        $errors = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $line = $i + 1; // nomor baris seperti di Excel
            $name = trim((string) ($row[$map['name']] ?? ''));
            $rowErrors = [];
            if ($name === '') {
                $rowErrors[] = 'nama kosong';
            } elseif (mb_strlen($name) > 255) {
                $rowErrors[] = 'nama terlalu panjang';
            }

            $answers = [];
            foreach (range(1, 10) as $num) {
                $value = $this->parseAnswer($row[$map["q$num"]] ?? null);
                if ($value === null) {
                    $rowErrors[] = "Q$num tidak valid (\"" . trim((string) ($row[$map["q$num"]] ?? '')) . '")';
                }
                $answers["q$num"] = $value;
            }

            if ($rowErrors) {
                $errors[] = "Baris $line: " . implode(', ', $rowErrors) . '.';

                continue;
            }

            $email = isset($map['email']) ? trim((string) ($row[$map['email']] ?? '')) : '';
            $feedback = isset($map['feedback']) ? trim((string) ($row[$map['feedback']] ?? '')) : '';

            $records[] = [
                'respondent_name' => $name,
                'respondent_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'feedback' => $feedback !== '' ? mb_substr($feedback, 0, 2000) : null,
                'filled_at' => isset($map['time']) ? $this->parseTime($row[$map['time']] ?? null) : null,
                ...$answers,
            ];
        }

        $columns = $this->describeColumns($rows[$headerIndex], $map);
        if ($errors) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => $errors, 'columns' => $columns];
        }
        if (! $records) {
            return $this->fail('Tidak ada baris jawaban di bawah baris judul.', $columns);
        }

        $imported = 0;
        $skipped = 0;
        DB::transaction(function () use ($records, &$imported, &$skipped) {
            foreach ($records as $record) {
                if ($this->alreadyExists($record)) {
                    $skipped++;

                    continue;
                }
                $response = new SusResponse(collect($record)->except('filled_at')->all());
                $response->source = 'import';
                if ($record['filled_at']) {
                    $response->created_at = $record['filled_at'];
                    $response->updated_at = $record['filled_at'];
                }
                $response->save();
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => [], 'columns' => $columns];
    }

    /** Cari baris judul (di 10 baris pertama) yang paling banyak kolomnya dikenali. */
    private function findHeader(array $rows): array
    {
        $best = [null, []];
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $map = $this->mapColumns($row);
            if (count($map) > count($best[1])) {
                $best = [$i, $map];
            }
        }

        return $best[0] === null || count($best[1]) < 2 ? [null, []] : $best;
    }

    /** @return array<string,int> kunci (name/email/time/feedback/q1..q10) => indeks kolom */
    private function mapColumns(array $header): array
    {
        $map = [];
        $statements = array_map(fn ($t) => $this->normalize($t), SusResponse::QUESTIONS);

        foreach ($header as $col => $raw) {
            $text = $this->normalize((string) $raw);
            if ($text === '') {
                continue;
            }

            $key = null;
            // 1) Teks pernyataan SUS (judul pertanyaan Google Form).
            foreach ($statements as $num => $statement) {
                if (str_contains($text, $statement) || (mb_strlen($text) > 20 && str_contains($statement, $text))) {
                    $key = "q$num";
                    break;
                }
            }
            // 2) "Q1", "P1", "Pertanyaan 1", "1", "1. ...".
            if (! $key && preg_match('/^(?:q|p|pertanyaan|soal|no)?\s*(\d{1,2})(?!\d)/', $text, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 10) {
                $key = 'q' . (int) $m[1];
            }
            if (! $key) {
                $key = match (true) {
                    str_contains($text, 'email') || str_contains($text, 'e mail') || str_contains($text, 'surel') => 'email',
                    str_contains($text, 'nama') || $text === 'name' || str_contains($text, 'full name') => 'name',
                    str_contains($text, 'kesan') || str_contains($text, 'saran') || str_contains($text, 'feedback') || str_contains($text, 'komentar') => 'feedback',
                    str_contains($text, 'timestamp') || str_contains($text, 'stempel waktu') || $text === 'waktu' || str_contains($text, 'waktu isi') || str_contains($text, 'tanggal') => 'time',
                    default => null,
                };
            }

            if ($key && ! isset($map[$key])) {
                $map[$key] = $col;
            }
        }

        return $map;
    }

    private function parseAnswer(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return ($value == (int) $value && $value >= 1 && $value <= 5) ? (int) $value : null;
        }
        $raw = str_replace(',', '.', trim((string) $value));
        if (is_numeric($raw)) {
            $number = (float) $raw;

            return ($number == (int) $number && $number >= 1 && $number <= 5) ? (int) $number : null;
        }
        $text = $this->normalize($raw);
        if ($text === '') {
            return null;
        }
        // "5 - Sangat Setuju", "4 (Setuju)"
        if (preg_match('/^([1-5])(?:\s|-|$)/', $text, $m)) {
            return (int) $m[1];
        }
        foreach (self::LIKERT as $label => $score) {
            if (str_contains($text, $label)) {
                return $score;
            }
        }

        return null;
    }

    private function parseTime(mixed $value): ?Carbon
    {
        try {
            if (is_numeric($value) && $value > 20000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }
            $text = trim((string) $value);
            if ($text === '') {
                return null;
            }
            foreach (['d/m/Y H:i:s', 'd/m/Y H.i.s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
                try {
                    $parsed = Carbon::createFromFormat('!' . $format, $text);
                } catch (Throwable) {
                    continue;
                }
                if ($parsed && $parsed->format($format) === $text) {
                    return $parsed;
                }
            }

            return Carbon::parse($text);
        } catch (Throwable) {
            return null;
        }
    }

    /** Jawaban yang sama persis (nama, 10 angka, dan waktu isi) tidak dimasukkan dua kali. */
    private function alreadyExists(array $record): bool
    {
        $query = SusResponse::where('respondent_name', $record['respondent_name']);
        foreach (range(1, 10) as $num) {
            $query->where("q$num", $record["q$num"]);
        }
        if ($record['filled_at']) {
            $query->where('created_at', $record['filled_at']);
        }

        return $query->exists();
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,string> kunci => judul kolom asli di file */
    private function describeColumns(array $header, array $map): array
    {
        $labels = ['name' => 'Nama', 'email' => 'Email', 'time' => 'Waktu isi', 'feedback' => 'Kesan & Saran'];
        $out = [];
        foreach ($map as $key => $col) {
            $out[$labels[$key] ?? strtoupper($key)] = mb_strimwidth((string) $header[$col], 0, 60, '…');
        }

        return $out;
    }

    private function fail(string $message, array $columns = []): array
    {
        return ['imported' => 0, 'skipped' => 0, 'errors' => [$message], 'columns' => $columns];
    }
}
