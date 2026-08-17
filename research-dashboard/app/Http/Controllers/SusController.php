<?php

namespace App\Http\Controllers;

use App\Models\SusResponse;
use Illuminate\Http\Request;

class SusController extends Controller
{
    public function index()
    {
        $responses = SusResponse::orderByDesc('created_at')->paginate(20);
        $avgScore = SusResponse::avg('sus_score');
        $count = SusResponse::count();

        // Analisis: per-pertanyaan (aspek mana yang paling lemah/kuat) +
        // sebaran kategori interpretasi -- lihat komentar di model untuk
        // rumus lengkapnya. Query terpisah dari $responses (paginated)
        // supaya analisisnya selalu berdasarkan SELURUH data, bukan cuma
        // 20 baris yang sedang tampil di halaman ini.
        $questionBreakdown = SusResponse::perQuestionBreakdown();
        $gradeDistribution = SusResponse::gradeDistribution();

        $weakestQuestion = $count > 0
            ? collect($questionBreakdown)->sortBy('avg_contribution')->first()
            : null;
        $strongestQuestion = $count > 0
            ? collect($questionBreakdown)->sortByDesc('avg_contribution')->first()
            : null;

        return view('sus.index', compact(
            'responses',
            'avgScore',
            'count',
            'questionBreakdown',
            'gradeDistribution',
            'weakestQuestion',
            'strongestQuestion'
        ));
    }

    /**
     * Form publik -- link ini yang dibagikan ke 150 responden setelah
     * mereka selesai memakai app KosKita, tanpa perlu login.
     */
    public function create()
    {
        return view('sus.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'respondent_name' => 'nullable|string|max:255',
            'source_user_id' => 'nullable|integer',
            'q1' => 'required|integer|min:1|max:5',
            'q2' => 'required|integer|min:1|max:5',
            'q3' => 'required|integer|min:1|max:5',
            'q4' => 'required|integer|min:1|max:5',
            'q5' => 'required|integer|min:1|max:5',
            'q6' => 'required|integer|min:1|max:5',
            'q7' => 'required|integer|min:1|max:5',
            'q8' => 'required|integer|min:1|max:5',
            'q9' => 'required|integer|min:1|max:5',
            'q10' => 'required|integer|min:1|max:5',
        ]);

        SusResponse::create($validated);

        return redirect()->route('sus.create')->with('success', 'Terima kasih! Jawabanmu berhasil disimpan.');
    }
}
