<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pengisian kuesioner System Usability Scale (Brooke, 1996) oleh
 * responden. sus_score sudah dihitung backend saat responden mengirim
 * form; calculateScore() di sini rumus yang sama untuk verifikasi & test.
 */
class SusResponse extends Model
{
    /**
     * Jawaban responden dibaca langsung dari tabel sus_responses milik
     * backend KosKita (diisi lewat form online /kuesioner di server), bukan
     * dari database lokal dashboard -- supaya analisis Bab IV memakai satu
     * sumber data yang sama dengan rating responden. Dashboard tidak pernah
     * menulis ke koneksi ini.
     */
    protected $connection = 'source';

    protected $table = 'sus_responses';

    protected $casts = [
        'sus_score' => 'float',
    ];

    /**
     * Pernyataan SUS adaptasi bahasa Indonesia yang sudah divalidasi
     * (Sharfina & Santoso, ICACSIS 2016, doi:10.1109/ICACSIS.2016.7872776;
     * Cronbach's alpha 0,841) -- disalin kata per kata, JANGAN diparafrasekan:
     * terjemahan bebas tidak punya bukti reliabilitas, jadi skornya tidak
     * bisa dibandingkan dengan norma SUS. "Sistem" = aplikasi KosKita, dan
     * itu dijelaskan di petunjuk form, bukan dengan mengubah kalimatnya.
     *
     * Satu sumber kebenaran untuk form pengisian (sus.create) maupun analisis
     * per-pertanyaan di dashboard, dan harus sama dengan Tabel 4.27 / Lampiran E
     * Buku TA.
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

    /** Pertanyaan bernomor GANJIL bernada positif, GENAP bernada negatif -- pola baku SUS. */
    public const POSITIVE_QUESTIONS = [1, 3, 5, 7, 9];

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
     * Peringkat CGS Sauro-Lewis untuk sebuah skor SUS (lihat CGS). Dulu
     * memakai campuran batas Bangor et al. dan CGS yang tidak bersumber;
     * kini satu tabel yang sama dengan Tabel 4.28 Buku TA.
     */
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
        // Skor rata-rata bisa jatuh di celah pembulatan antar rentang tabel
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
     * Sebaran jumlah responden per peringkat CGS -- dipakai chart "berapa
     * responden yang memberi skor setara A+/A/.../F".
     *
     * @return array<string, int>
     */
    public static function gradeDistribution(): array
    {
        $grades = array_fill_keys(array_column(self::CGS, 1), 0);

        self::pluck('sus_score')->each(function (float $score) use (&$grades) {
            $grades[self::interpret($score)]++;
        });

        return $grades;
    }
}
