<?php

namespace App\Http\Controllers;

use App\Models\SusResponse;
use App\Services\Sus\SusDataset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SusController extends Controller
{
    public function index(Request $request)
    {
        // ?sumber= memilih sumber responden (lihat SusDataset::SOURCES),
        // ?kualitas=bersih mengecualikan jawaban bertanda (sama semua /
        // kontradiktif) dari seluruh perhitungan di halaman ini.
        $source = array_key_exists((string) $request->query('sumber'), SusDataset::SOURCES)
            ? (string) $request->query('sumber')
            : 'semua';
        $cleanOnly = $request->query('kualitas') === 'bersih';

        $sourceRows = SusDataset::rows($source);
        $rows = $cleanOnly ? SusDataset::withoutFlagged($sourceRows) : $sourceRows;

        $summary = SusDataset::summary($rows);
        $cleanSummary = SusDataset::summary(SusDataset::withoutFlagged($sourceRows));
        $flagCounts = SusDataset::flagCounts($sourceRows);
        $sourceCounts = SusDataset::rows()->countBy('source');
        $demographics = SusDataset::populixDemographics($rows);

        $avgScore = $summary['mean'];
        $count = $summary['count'];

        // Analisis: per-pertanyaan (aspek mana yang paling lemah/kuat) +
        // sebaran kategori interpretasi -- lihat komentar di model untuk
        // rumus lengkapnya. Dihitung dari SELURUH baris terpilih, bukan cuma
        // 20 baris yang sedang tampil di halaman ini.
        $questionBreakdown = SusResponse::perQuestionBreakdown($rows);
        $gradeDistribution = SusResponse::gradeDistribution($rows);

        $weakestQuestion = $count > 0
            ? collect($questionBreakdown)->sortBy('avg_contribution')->first()
            : null;
        $strongestQuestion = $count > 0
            ? collect($questionBreakdown)->sortByDesc('avg_contribution')->first()
            : null;

        $page = LengthAwarePaginator::resolveCurrentPage();
        $responses = new LengthAwarePaginator(
            $rows->forPage($page, 20)->values(),
            $count,
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('sus.index', compact(
            'responses',
            'avgScore',
            'count',
            'summary',
            'cleanSummary',
            'flagCounts',
            'sourceCounts',
            'source',
            'cleanOnly',
            'demographics',
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
