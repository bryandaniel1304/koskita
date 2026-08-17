<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pengisian kuesioner System Usability Scale (Brooke, 1996) oleh
 * responden. Skor dihitung otomatis pakai formula baku SUS (lihat
 * calculateScore()) saat record dibuat.
 */
class SusResponse extends Model
{
    protected $fillable = [
        'source_user_id',
        'respondent_name',
        'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10',
        'sus_score',
    ];

    /**
     * Teks pernyataan SUS resmi (Brooke, 1996), diterjemahkan -- satu
     * sumber kebenaran dipakai baik oleh form pengisian (sus.create) MAUPUN
     * analisis per-pertanyaan di dashboard, supaya labelnya tidak pernah
     * saling drift kalau nanti diubah.
     */
    public const QUESTIONS = [
        1 => 'Saya berpikir akan menggunakan aplikasi ini lagi.',
        2 => 'Saya merasa aplikasi ini rumit untuk digunakan.',
        3 => 'Saya merasa aplikasi ini mudah digunakan.',
        4 => 'Saya membutuhkan bantuan orang lain/teknisi untuk bisa menggunakan aplikasi ini.',
        5 => 'Saya merasa fitur-fitur di aplikasi ini berjalan dengan semestinya (terintegrasi baik).',
        6 => 'Saya merasa ada banyak hal yang tidak konsisten pada aplikasi ini.',
        7 => 'Saya merasa kebanyakan orang akan cepat memahami cara memakai aplikasi ini.',
        8 => 'Saya merasa aplikasi ini membingungkan/merepotkan untuk dipakai.',
        9 => 'Saya merasa yakin/percaya diri saat menggunakan aplikasi ini.',
        10 => 'Saya perlu membiasakan diri dulu sebelum bisa lancar memakai aplikasi ini.',
    ];

    /** Pertanyaan bernomor GANJIL bernada positif, GENAP bernada negatif -- pola baku SUS. */
    public const POSITIVE_QUESTIONS = [1, 3, 5, 7, 9];

    protected static function booted(): void
    {
        static::creating(function (SusResponse $response) {
            $response->sus_score = self::calculateScore([
                $response->q1, $response->q2, $response->q3, $response->q4, $response->q5,
                $response->q6, $response->q7, $response->q8, $response->q9, $response->q10,
            ]);
        });
    }

    /**
     * Formula baku SUS: pertanyaan ganjil (positif) kontribusinya
     * (jawaban - 1), pertanyaan genap (negatif) kontribusinya
     * (5 - jawaban). Total (0-40) dikali 2.5 supaya hasilnya 0-100.
     *
     * @param array<int, int> $answers 10 jawaban skala Likert 1-5, q1..q10 berurutan.
     */
    public static function calculateScore(array $answers): float
    {
        $total = 0;
        foreach ($answers as $i => $answer) {
            $isOdd = ($i % 2) === 0; // index 0 = q1 (ganjil), index 1 = q2 (genap), dst.
            $total += $isOdd ? ($answer - 1) : (5 - $answer);
        }

        return $total * 2.5;
    }

    /**
     * Interpretasi skor SUS memakai skala adjektif Bangor et al. (2009) --
     * dipakai di dashboard supaya angka mentah lebih mudah dimaknai untuk
     * Bab IV skripsi.
     */
    public static function interpret(float $score): string
    {
        return match (true) {
            $score >= 85.5 => 'Best Imaginable',
            $score >= 80.8 => 'Excellent',
            $score >= 68.0 => 'Good',
            $score >= 51.0 => 'OK',
            $score >= 25.0 => 'Poor',
            default => 'Worst Imaginable',
        };
    }

    /**
     * Rata-rata "kontribusi positif" tiap pertanyaan (0-4, makin tinggi
     * makin baik) -- pertanyaan negatif (genap) SUDAH dibalik pakai rumus
     * yang sama dengan calculateScore(), jadi ke-10 pertanyaan bisa
     * dibandingkan langsung di satu skala yang sama, bukan skala mentah
     * 1-5 yang arahnya bolak-balik tiap nomor genap/ganjil. Dipakai untuk
     * cari aspek aplikasi yang paling lemah/kuat menurut responden.
     *
     * @return array<int, array{question:int, text:string, avg_raw:float, avg_contribution:float}>
     */
    public static function perQuestionBreakdown(): array
    {
        $responses = self::all(['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10']);
        $count = $responses->count();

        $breakdown = [];
        foreach (self::QUESTIONS as $num => $text) {
            $column = "q$num";
            $avgRaw = $count > 0 ? $responses->avg($column) : 0.0;
            $isPositive = in_array($num, self::POSITIVE_QUESTIONS, true);
            $avgContribution = $isPositive ? ($avgRaw - 1) : (5 - $avgRaw);

            $breakdown[] = [
                'question' => $num,
                'text' => $text,
                'avg_raw' => round($avgRaw, 2),
                'avg_contribution' => round($avgContribution, 2),
            ];
        }

        return $breakdown;
    }

    /**
     * Sebaran jumlah responden per kategori interpretasi Bangor et al. --
     * dipakai chart "berapa persen responden yang menilai Excellent/Good/dst".
     *
     * @return array<string, int>
     */
    public static function gradeDistribution(): array
    {
        $grades = ['Best Imaginable' => 0, 'Excellent' => 0, 'Good' => 0, 'OK' => 0, 'Poor' => 0, 'Worst Imaginable' => 0];

        self::pluck('sus_score')->each(function (float $score) use (&$grades) {
            $grades[self::interpret($score)]++;
        });

        return $grades;
    }
}
