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

    /** Skala adjektif Bangor et al. (2009). */
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

    /** @return array<string, int> jumlah responden per kategori Bangor et al. */
    public static function gradeDistribution(): array
    {
        $grades = ['Best Imaginable' => 0, 'Excellent' => 0, 'Good' => 0, 'OK' => 0, 'Poor' => 0, 'Worst Imaginable' => 0];

        foreach (self::pluck('sus_score') as $score) {
            $grades[self::interpret((float) $score)]++;
        }

        return $grades;
    }
}
