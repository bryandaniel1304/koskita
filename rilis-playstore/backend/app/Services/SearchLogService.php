<?php

namespace App\Services;

use App\Models\SearchLog;
use Illuminate\Http\Request;

/**
 * Catat pencarian kos yang hasilnya NIHIL -- dipanggil dari
 * Api\KosController::index() (mobile) & Web\WebKosController::index()
 * (web), sengaja SATU tempat supaya format yang tersimpan konsisten
 * antara kedua platform. Tidak mencatat pencarian yang memang tanpa
 * filter (buka /kos begitu saja) -- itu bukan "pencarian gagal", itu
 * cuma buka daftar kos biasa.
 */
class SearchLogService
{
    public static function logIfEmpty(Request $request, int $resultsCount): void
    {
        if ($resultsCount > 0) {
            return;
        }

        $facilityIds = array_values(array_filter((array) $request->input('facilities', [])));

        $hasFilter = $request->filled('search')
            || $request->filled('location')
            || $request->filled('gender_type')
            || $request->filled('budget_min')
            || $request->filled('budget_max')
            || !empty($facilityIds);

        if (!$hasFilter) {
            return;
        }

        try {
            SearchLog::create([
                'user_id' => $request->user()?->id,
                'keyword' => $request->input('search'),
                'location' => $request->input('location'),
                'gender_type' => $request->input('gender_type'),
                'budget_min' => $request->input('budget_min'),
                'budget_max' => $request->input('budget_max'),
                'facility_ids' => $facilityIds ?: null,
            ]);
        } catch (\Throwable $e) {
            // Gagal mencatat analitik TIDAK BOLEH menggagalkan pencarian
            // itu sendiri -- ini murni observability, bukan fitur inti.
            report($e);
        }
    }
}
