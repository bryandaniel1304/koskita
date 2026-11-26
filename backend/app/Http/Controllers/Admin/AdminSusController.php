<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SusResponse;
use Illuminate\Http\Request;

/**
 * Hasil kuesioner SUS dari uji coba online -- ringkasan skor, aspek
 * terlemah/terkuat, sebaran peringkat CGS Sauro-Lewis, dan ekspor CSV untuk
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
            fputcsv($out, ['No', 'Nama', 'Email', ...$questionColumns, 'Skor SUS', 'Peringkat CGS', 'Kesan & Saran', 'Waktu Isi']);
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

    /*
     * Koreksi & pembersihan data (khusus Master Admin, lihat routes/web.php).
     */

    public function edit($id)
    {
        $response = SusResponse::findOrFail($id);

        return view('admin.sus.edit', compact('response'));
    }

    public function update(Request $request, $id)
    {
        $response = SusResponse::findOrFail($id);

        $rules = [
            'respondent_name' => 'required|string|max:255',
            'respondent_email' => 'nullable|email|max:255',
            'feedback' => 'nullable|string|max:2000',
        ];
        foreach (array_keys(SusResponse::QUESTIONS) as $num) {
            $rules["q$num"] = 'required|integer|between:1,5';
        }
        $validated = $request->validate($rules);

        $response->fill($validated)->save();

        return redirect()->route('admin.sus.index')->with('success', 'Jawaban ' . $response->respondent_name . ' berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $response = SusResponse::findOrFail($id);
        $response->delete();

        return redirect()->route('admin.sus.index')->with('success', 'Jawaban ' . $response->respondent_name . ' berhasil dihapus.');
    }

    /** Hapus beberapa jawaban sekaligus (mis. data uji coba). */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = SusResponse::whereIn('id', $validated['ids'])->delete();

        return redirect()->route('admin.sus.index')->with('success', $deleted . ' jawaban berhasil dihapus.');
    }
}
