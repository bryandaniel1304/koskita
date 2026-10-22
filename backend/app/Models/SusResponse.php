<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pengisian kuesioner System Usability Scale (Brooke, 1996) dari
 * responden uji coba online. Skor dihitung otomatis dengan rumus baku SUS
 * saat record dibuat. Teks pertanyaan & rumus disamakan persis dengan
 * research-dashboard supaya hasil kedua sumber bisa dibandingkan.
 */
class SusResponse extends Model
{
    protected $fillable = [
        'user_id',
        'respondent_name',
        'respondent_email',
        'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10',
        'feedback',
    ];

    protected $casts = [
        'sus_score' => 'float',
    ];

    /**
     * Pernyataan SUS adaptasi bahasa Indonesia yang sudah divalidasi
     * (Sharfina & Santoso, ICACSIS 2016, doi:10.1109/ICACSIS.2016.7872776;
     * Cronbach's alpha 0,841) -- disalin kata per kata, JANGAN diparafrasekan:
     * terjemahan bebas tidak punya bukti reliabilitas. "Sistem" = aplikasi
     * KosKita, dijelaskan di petunjuk form. Harus sama dengan Tabel 4.27 /
     * Lampiran E Buku TA dan research-dashboard.
     */
    public const QUESTIONS = [
        1 => 'Saya berpikir akan menggunakan sistem ini lagi.',
        2 => 'Saya merasa sistem ini rumit untuk digunakan.',
        3 => 'Saya merasa sistem ini mudah digunakan.',
        4 => 'Saya membutuhkan bantuan dari orang lain atau teknisi dalam menggunakan sistem ini.',
        5 => 'Saya merasa fitur-fitur sistem ini berjalan dengan semestinya.',
        6 => 'Saya merasa ada banyak hal yang tidak konsisten (tidak serasi pada sistem ini).',
        7 => 'Saya merasa orang lain akan memahami cara menggunakan sistem ini dengan cepat.',
        8 => 'Saya merasa sistem ini membingungkan.',
        9 => 'Saya merasa tidak ada hambatan dalam menggunakan sistem ini.',
        10 => 'Saya perlu membiasakan diri terlebih dahulu sebelum menggunakan sistem ini.',
    ];

    /**
     * Curved Grading Scale Sauro-Lewis (Lewis, 2018, Tabel 1; Sauro & Lewis,
     * 2016): batas bawah skor => [peringkat, rentang persentil]. Rata-rata
     * data acuannya 68, tepat di tengah peringkat C.
     */
    public const CGS = [
        [84.1, 'A+', '96–100'],
        [80.8, 'A', '90–95'],
        [78.9, 'A-', '85–89'],
        [77.2, 'B+', '80–84'],
        [74.1, 'B', '70–79'],
        [72.6, 'B-', '65–69'],
        [71.1, 'C+', '60–64'],
        [65.0, 'C', '41–59'],
        [62.7, 'C-', '35–40'],
        [51.7, 'D', '15–34'],
        [0.0, 'F', '0–14'],
    ];

    /** Nomor GANJIL bernada positif, GENAP bernada negatif -- pola baku SUS. */
    public const POSITIVE_QUESTIONS = [1, 3, 5, 7, 9];

    protected static function booted(): void
    {
        static::creating(function (SusResponse $response) {
            $response->sus_score = self::calculateScore(array_map(
                fn (int $num) => (int) $response->{"q$num"},
                array_keys(self::QUESTIONS)
            ));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rumus baku SUS: pertanyaan ganjil menyumbang (jawaban - 1), genap
     * menyumbang (5 - jawaban). Total 0-40 dikali 2,5 jadi skor 0-100.
     *
     * @param array<int, int> $answers 10 jawaban 1-5, q1..q10 berurutan.
     */
    public static function calculateScore(array $answers): float
    {
        $total = 0;
        foreach (array_values($answers) as $i => $answer) {
            $total += ($i % 2 === 0) ? ($answer - 1) : (5 - $answer);
        }

        return $total * 2.5;
    }

    /** Peringkat CGS Sauro-Lewis untuk sebuah skor SUS (lihat CGS). */
    public static function interpret(float $score): string
    {
        return self::cgsRow($score)[1];
    }

    /** Rentang persentil CGS untuk sebuah skor SUS, mis. "41–59". */
    public static function percentileRange(float $score): string
    {
        return self::cgsRow($score)[2];
    }

    /** @return array{0: float, 1: string, 2: string} */
    private static function cgsRow(float $score): array
    {
        // Rata-rata bisa jatuh di celah pembulatan antar rentang tabel
        // (mis. 84,05), jadi dibulatkan ke satu desimal seperti tabelnya.
        $rounded = round($score, 1);
        foreach (self::CGS as $row) {
            if ($rounded >= $row[0]) {
                return $row;
            }
        }

        return self::CGS[array_key_last(self::CGS)];
    }

    /**
     * Rata-rata kontribusi tiap pertanyaan (0-4, makin tinggi makin baik)
     * -- pertanyaan genap sudah dibalik, jadi ke-10 aspek bisa dibandingkan
     * langsung untuk mencari aspek yang paling lemah/kuat.
     *
     * @return array<int, array{question:int, text:string, avg_raw:float, avg_contribution:float}>
     */
    public static function perQuestionBreakdown(): array
    {
        $responses = self::all(array_map(fn (int $num) => "q$num", array_keys(self::QUESTIONS)));
        $count = $responses->count();

        $breakdown = [];
        foreach (self::QUESTIONS as $num => $text) {
            $avgRaw = $count > 0 ? (float) $responses->avg("q$num") : 0.0;
            $isPositive = in_array($num, self::POSITIVE_QUESTIONS, true);

            $breakdown[] = [
                'question' => $num,
                'text' => $text,
                'avg_raw' => round($avgRaw, 2),
                'avg_contribution' => $count > 0 ? round($isPositive ? $avgRaw - 1 : 5 - $avgRaw, 2) : 0.0,
            ];
        }

        return $breakdown;
    }

    /** @return array<string, int> jumlah responden per peringkat CGS */
    public static function gradeDistribution(): array
    {
        $grades = array_fill_keys(array_column(self::CGS, 1), 0);

        foreach (self::pluck('sus_score') as $score) {
            $grades[self::interpret((float) $score)]++;
        }

        return $grades;
    }
}
