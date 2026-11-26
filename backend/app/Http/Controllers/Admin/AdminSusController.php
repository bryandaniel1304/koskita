<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SusResponse;
use App\Services\SusImporter;
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
            fputcsv($out, ['No', 'Nama', 'Email', ...$questionColumns, 'Skor SUS', 'Peringkat CGS', 'Kesan & Saran', 'Waktu Isi', 'Sumber']);
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
                    $response->source === 'import' ? 'Impor file' : 'Form KosKita',
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

    /**
     * Unggah file jawaban (mis. ekspor Google Form). Kolom dikenali dari
     * judulnya, lihat SusImporter.
     */
    public function import(Request $request, SusImporter $importer)
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:xlsx,xls,csv,txt,ods',
        ], [
            'file.required' => 'Pilih file Excel/CSV terlebih dahulu.',
            'file.mimes' => 'Format file harus .xlsx, .xls, .csv, atau .ods.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $file = $request->file('file');
        $result = $importer->import($file->getRealPath(), $file->getClientOriginalName());

        if ($result['errors']) {
            return redirect()->route('admin.sus.index')
                ->with('import_errors', array_slice($result['errors'], 0, 15))
                ->with('import_error_total', count($result['errors']))
                ->with('import_columns', $result['columns']);
        }

        $message = $result['imported'] . ' jawaban berhasil diimpor dari ' . $file->getClientOriginalName() . '.';
        if ($result['skipped']) {
            $message .= ' ' . $result['skipped'] . ' baris dilewati karena sudah ada.';
        }

        return redirect()->route('admin.sus.index')->with('success', $message)->with('import_columns', $result['columns']);
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
