<?php

namespace App\Http\Controllers;

use App\Models\EvaluationRun;
use App\Services\Evaluation\EvaluationService;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function index()
    {
        $runs = EvaluationRun::orderByDesc('created_at')->limit(200)->get();
        $batches = $runs->groupBy('batch_label');

        // Batch TERBARU dari tiap jenis eksperimen -- dipakai buat grafik
        // ringkasan di atas tabel (lebih gampang dibaca saat sidang
        // daripada cuma angka di tabel). Deteksi jenis dari prefix label
        // ('baseline-...' / 'alpha-sweep-...'), bukan field terpisah,
        // supaya tidak perlu migration baru di tabel evaluation_runs.
        $latestBaselineLabel = $batches->keys()->filter(fn ($l) => str_starts_with($l, 'baseline-'))->sortDesc()->first();
        $latestAlphaSweepLabel = $batches->keys()->filter(fn ($l) => str_starts_with($l, 'alpha-sweep-'))->sortDesc()->first();

        $latestBaselineRuns = $latestBaselineLabel ? $batches[$latestBaselineLabel] : collect();
        $latestAlphaSweepRuns = $latestAlphaSweepLabel ? $batches[$latestAlphaSweepLabel] : collect();

        $baselineAnalysis = $this->analyzeBaselines($latestBaselineRuns);
        $alphaSweepAnalysis = $this->analyzeAlphaSweep($latestAlphaSweepRuns);

        return view('evaluation.index', compact(
            'batches',
            'latestBaselineLabel',
            'latestAlphaSweepLabel',
            'latestBaselineRuns',
            'latestAlphaSweepRuns',
            'baselineAnalysis',
            'alphaSweepAnalysis'
        ));
    }

    protected const METRICS = ['precision', 'recall', 'ndcg', 'map'];

    /**
     * Bandingkan hybrid melawan tiap baseline pada K=10 -- hitung persen
     * peningkatan per metrik supaya kesimpulan "hybrid lebih unggul X%"
     * di skripsi punya angka pasti, bukan cuma dibaca dari grafik.
     *
     * @return array{winner: string, comparisons: array}|null
     */
    protected function analyzeBaselines($runs): ?array
    {
        $runsK10 = $runs->where('k', 10)->keyBy('strategy');
        if (!$runsK10->has('hybrid') || $runsK10->count() < 2) {
            return null;
        }

        $hybrid = $runsK10['hybrid'];
        $comparisons = [];
        $strategyLabels = ['cb_only' => 'Content-Based Murni', 'cf_only' => 'Collaborative Filtering Murni', 'popularity' => 'Popularitas (non-personal)'];

        foreach ($strategyLabels as $key => $label) {
            if (!$runsK10->has($key)) {
                continue;
            }
            $baseline = $runsK10[$key];
            $metricDiffs = [];
            foreach (self::METRICS as $metric) {
                $baseValue = (float) $baseline->$metric;
                $hybridValue = (float) $hybrid->$metric;
                $pctChange = $baseValue > 0 ? (($hybridValue - $baseValue) / $baseValue) * 100 : ($hybridValue > 0 ? 100 : 0);
                $metricDiffs[$metric] = round($pctChange, 1);
            }
            $comparisons[] = ['label' => $label, 'strategy' => $key, 'diffs' => $metricDiffs];
        }

        // "Menang" = hybrid unggul di metrik yang lebih banyak dibanding kalah, dirata-ratakan lintas semua baseline.
        $avgDiffs = collect($comparisons)->flatMap(fn ($c) => $c['diffs'])->groupBy(fn ($v, $k) => $k)->map(fn ($g) => $g->avg());
        $winningMetrics = collect($avgDiffs)->filter(fn ($v) => $v > 0)->count();

        return [
            'hybrid' => $hybrid,
            'comparisons' => $comparisons,
            'winning_metrics_count' => $winningMetrics,
            'total_metrics' => count(self::METRICS),
        ];
    }

    /**
     * Cari alpha dengan performa terbaik (rata-rata ke-4 metrik) pada K=10
     * -- menjawab pertanyaan "alpha optimal berapa?" secara otomatis,
     * bukan cuma dari membaca puncak grafik secara visual.
     */
    protected function analyzeAlphaSweep($runs): ?array
    {
        $runsK10 = $runs->where('k', 10);
        if ($runsK10->isEmpty()) {
            return null;
        }

        $ranked = $runsK10->map(function ($run) {
            $avgMetric = collect(self::METRICS)->avg(fn ($m) => (float) $run->$m);
            return ['run' => $run, 'avg_metric' => $avgMetric];
        })->sortByDesc('avg_metric')->values();

        $best = $ranked->first();
        $productionAlpha = 0.6;
        $productionRun = $runsK10->first(fn ($r) => abs((float) $r->alpha - $productionAlpha) < 0.001);

        return [
            'best_alpha' => (float) $best['run']->alpha,
            'best_avg_metric' => round($best['avg_metric'], 4),
            'ranked' => $ranked,
            'matches_production' => $productionRun && abs((float) $productionRun->alpha - $best['run']->alpha) < 0.001,
            'production_run' => $productionRun,
        ];
    }

    /**
     * Jalankan perbandingan hybrid vs baseline (CB murni, CF murni,
     * popularitas) dengan alpha yang dipakai production (0.6 default).
     */
    public function runBaselines(Request $request, EvaluationService $service)
    {
        $alpha = (float) $request->input('alpha', 0.6);
        $kValues = [5, 10];
        $batchLabel = 'baseline-' . now()->format('Y-m-d_H-i-s');

        $results = $service->compareBaselines($alpha, $kValues);
        $this->persist($results, $kValues, $batchLabel);

        return redirect()->route('evaluation.index')->with('success', 'Evaluasi hybrid vs baseline selesai dijalankan.');
    }

    /**
     * Eksperimen mencari alpha optimal -- jalankan strategi hybrid untuk
     * beberapa nilai alpha sekaligus.
     */
    public function compareAlphas(EvaluationService $service)
    {
        $kValues = [5, 10];
        $batchLabel = 'alpha-sweep-' . now()->format('Y-m-d_H-i-s');

        $results = $service->compareAlphas([0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8], $kValues);
        $this->persist($results, $kValues, $batchLabel);

        return redirect()->route('evaluation.index')->with('success', 'Eksperimen perbandingan alpha selesai dijalankan.');
    }

    protected function persist(array $results, array $kValues, string $batchLabel): void
    {
        foreach ($results as $result) {
            foreach ($kValues as $k) {
                $m = $result['metrics_by_k'][$k];
                EvaluationRun::create([
                    'strategy' => $result['strategy'],
                    'alpha' => $result['alpha'],
                    'k' => $k,
                    'users_evaluated' => $result['users_evaluated'],
                    'precision' => $m['precision'],
                    'recall' => $m['recall'],
                    'ndcg' => $m['ndcg'],
                    'map' => $m['map'],
                    'batch_label' => $batchLabel,
                ]);
            }
        }
    }
}
