<?php

namespace App\Services\Sus;

use App\Models\PopulixSusResponse;
use App\Models\SusResponse;
use Illuminate\Support\Collection;

/**
 * Menyatukan jawaban SUS dari dua sumber -- kuesioner /kuesioner di server
 * KosKita (SusResponse, koneksi "source") dan panel Populix
 * (PopulixSusResponse, database dashboard) -- menjadi baris yang seragam,
 * supaya statistik dan grafik dihitung dengan rumus yang sama untuk sumber
 * mana pun.
 */
class SusDataset
{
    public const SOURCES = [
        'semua' => 'Semua sumber',
        'koskita' => 'Kuesioner KosKita',
        'populix' => 'Panel Populix',
    ];

    /** Batas "di atas rata-rata": rata-rata data acuan CGS Sauro-Lewis. */
    public const THRESHOLD = 68.0;

    /**
     * @return Collection<int, array<string, mixed>> terurut dari yang terbaru
     */
    public static function rows(string $source = 'semua'): Collection
    {
        $rows = collect();

        if ($source !== 'populix') {
            foreach (SusResponse::all() as $r) {
                $rows->push(self::row('koskita', $r, $r->respondent_name ?: 'Anonim', $r->created_at, [
                    'email' => $r->respondent_email,
                ]));
            }
        }

        if ($source !== 'koskita') {
            foreach (PopulixSusResponse::all() as $r) {
                $rows->push(self::row('populix', $r, 'Populix #' . $r->populix_id, $r->completed_at ?? $r->created_at, [
                    'gender' => $r->gender,
                    'age' => $r->age,
                    'province' => $r->province,
                    'city' => $r->city,
                ]));
            }
        }

        return $rows->sortByDesc(fn ($row) => $row['submitted_at']?->timestamp ?? 0)->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public static function withoutFlagged(Collection $rows): Collection
    {
        return $rows->reject(fn ($row) => $row['flagged'])->values();
    }

    /**
     * Statistik deskriptif skor SUS. Simpangan baku memakai rumus sampel
     * (n - 1), sama dengan STDEV di Excel.
     *
     * @param Collection<int, array<string, mixed>> $rows
     * @return array{count:int, mean:?float, sd:?float, median:?float, min:?float, max:?float, above_threshold:int}
     */
    public static function summary(Collection $rows): array
    {
        $scores = $rows->pluck('sus_score');
        $count = $scores->count();
        $mean = $count > 0 ? $scores->avg() : null;
        $sd = $count > 1
            ? sqrt($scores->sum(fn ($s) => ($s - $mean) ** 2) / ($count - 1))
            : null;

        return [
            'count' => $count,
            'mean' => $mean,
            'sd' => $sd,
            'median' => $count > 0 ? (float) $scores->median() : null,
            'min' => $count > 0 ? $scores->min() : null,
            'max' => $count > 0 ? $scores->max() : null,
            'above_threshold' => $scores->filter(fn ($s) => $s >= self::THRESHOLD)->count(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array{straight_lining:int, contradictory:int, flagged:int}
     */
    public static function flagCounts(Collection $rows): array
    {
        return [
            'straight_lining' => $rows->where('straight_lining', true)->count(),
            'contradictory' => $rows->where('contradictory', true)->count(),
            'flagged' => $rows->where('flagged', true)->count(),
        ];
    }

    /**
     * Karakteristik responden Populix (Populix menyertakan gender, usia, dan
     * domisili; kuesioner KosKita tidak).
     *
     * @param Collection<int, array<string, mixed>> $rows
     * @return array{count:int, gender:Collection, age_min:?int, age_max:?int, age_mean:?float, provinces:Collection}|null
     */
    public static function populixDemographics(Collection $rows): ?array
    {
        $populix = $rows->where('source', 'populix');
        if ($populix->isEmpty()) {
            return null;
        }

        $ages = $populix->pluck('age')->filter(fn ($age) => $age !== null);

        return [
            'count' => $populix->count(),
            'gender' => $populix->pluck('gender')->filter()->countBy()->sortDesc(),
            'age_min' => $ages->min(),
            'age_max' => $ages->max(),
            'age_mean' => $ages->isNotEmpty() ? $ages->avg() : null,
            'provinces' => $populix->pluck('province')->filter()->countBy()->sortDesc(),
        ];
    }

    private static function row(string $source, object $response, string $label, $submittedAt, array $extra): array
    {
        $answers = array_map(fn ($n) => (int) $response->{"q$n"}, range(1, 10));
        $straightLining = SusResponse::isStraightLining($answers);
        $contradictory = SusResponse::isContradictory($answers);

        return [
            'source' => $source,
            'label' => $label,
            'answers' => $answers,
            'sus_score' => (float) $response->sus_score,
            'submitted_at' => $submittedAt,
            'straight_lining' => $straightLining,
            'contradictory' => $contradictory,
            'flagged' => $straightLining || $contradictory,
        ] + array_combine(array_map(fn ($n) => "q$n", range(1, 10)), $answers) + $extra;
    }
}
