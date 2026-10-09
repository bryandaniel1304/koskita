<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SusResponse;

/**
 * Hasil kuesioner SUS dari uji coba online -- ringkasan skor, aspek
 * terlemah/terkuat, sebaran kategori Bangor et al., dan ekspor CSV untuk
 * diolah di Bab IV.
 */
class AdminSusController extends Controller
{
    public function index()
    {
        $count = SusResponse::count();
        $avgScore = $count > 0 ? round((float) SusResponse::avg('sus_score'), 2) : null;
        $questionBreakdown = SusResponse::perQuestionBreakdown();
        $gradeDistribution = SusResponse::gradeDistribution();
        $responses = SusResponse::latest()->paginate(20);

        return view('admin.sus.index', compact('count', 'avgScore', 'questionBreakdown', 'gradeDistribution', 'responses'));
    }

    public function exportCsv()
    {
        $responses = SusResponse::oldest()->get();
        $filename = 'kuesioner-sus-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($responses) {
            $out = fopen('php://output', 'w');
            $questionColumns = array_map(fn (int $num) => "Q$num", array_keys(SusResponse::QUESTIONS));
            fputcsv($out, ['No', 'Nama', 'Email', ...$questionColumns, 'Skor SUS', 'Kategori', 'Kesan & Saran', 'Waktu Isi']);
            foreach ($responses as $i => $response) {
                fputcsv($out, [
                    $i + 1,
                    $response->respondent_name,
                    $response->respondent_email,
                    ...array_map(fn (int $num) => $response->{"q$num"}, array_keys(SusResponse::QUESTIONS)),
                    $response->sus_score,
                    SusResponse::interpret($response->sus_score),
                    $response->feedback ?? '',
                    $response->created_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
