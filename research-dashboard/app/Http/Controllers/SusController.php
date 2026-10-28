<?php

namespace App\Http\Controllers;

use App\Models\SusResponse;

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
     * Kuesioner resmi diisi responden di server (/kuesioner, satu akun satu
     * kali) -- dashboard tidak lagi punya form sendiri supaya semua jawaban
     * berada di satu tabel yang sama dengan data rating responden.
     */
    public function create()
    {
        return redirect()->away(config('services.koskita.questionnaire_url'));
    }
}
