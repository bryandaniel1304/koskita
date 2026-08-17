@extends('layouts.app')

@section('title', 'Evaluasi')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Evaluasi Rekomendasi</h4>
        <p class="text-muted mb-0">Precision@K, Recall@K, NDCG@K, dan MAP@K dihitung dari data interaksi responden asli via protokol holdout (lihat komentar di <code>EvaluationService</code>). Butuh minimal beberapa responden dengan ≥2 rating bintang 4-5 supaya hasilnya bermakna.</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">🖨️ Cetak / Simpan sebagai PDF</button>
</div>

<div class="row g-3 mb-4 no-print">
    <div class="col-md-6">
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-2">1. Hybrid vs Baseline</h6>
            <p class="small text-muted">Bandingkan model hybrid (alpha production = 0.6) melawan CB murni, CF murni, dan popularitas non-personal -- pembuktian kontribusi ilmiah model hybrid (skripsi Bab III).</p>
            <form method="POST" action="{{ route('evaluation.run-baselines') }}">
                @csrf
                <div class="mb-3">
                    <label for="alphaInput" class="form-label small fw-bold text-muted">Nilai Alpha (α) untuk Hybrid:</label>
                    <div class="d-flex align-items-center gap-3">
                        <input type="range" class="form-range" id="alphaInput" name="alpha" min="0.1" max="0.9" step="0.1" value="0.6" oninput="this.nextElementSibling.value = this.value" style="flex: 1;">
                        <output class="fw-bold text-primary" style="min-width: 25px;">0.6</output>
                    </div>
                    <div class="text-muted" style="font-size: 11px; margin-top: 4px;">
                        α → 1.0 (Dominan Content-Based) | α → 0.0 (Dominan Collaborative Filtering)
                    </div>
                </div>
                <button type="submit" class="btn btn-primary-custom">Jalankan Evaluasi Hybrid vs Baseline</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-2">2. Eksperimen Cari Alpha Optimal</h6>
            <p class="small text-muted">Jalankan strategi hybrid untuk alpha = 0.2 s/d 0.8, supaya bisa dibandingkan mana yang metriknya paling tinggi -- menjawab "alpha ditentukan lewat eksperimen" di skripsi.</p>
            <form method="POST" action="{{ route('evaluation.compare-alphas') }}">
                @csrf
                <button type="submit" class="btn btn-primary-custom">Jalankan Perbandingan Alpha</button>
            </form>
        </div>
    </div>
</div>

@if($latestBaselineRuns->isNotEmpty())
    <div class="card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">📊 Grafik: Hybrid vs Baseline (batch terbaru -- K=10)</h6>
            <button class="btn btn-sm btn-outline-secondary no-print" onclick="downloadChartPng('chartBaseline', 'hybrid-vs-baseline.png')">⬇️ Unduh PNG</button>
        </div>
        <p class="small text-muted">Batch: {{ $latestBaselineLabel }}</p>
        <canvas id="chartBaseline" height="90"></canvas>
    </div>

    @if($baselineAnalysis)
        <div class="card-custom p-4 mb-4" style="border-left: 4px solid var(--primary);">
            <h6 class="fw-bold mb-2">📝 Kesimpulan: Hybrid vs Baseline</h6>
            <p class="mb-2">
                Model <strong>Hybrid (α = {{ number_format($baselineAnalysis['hybrid']->alpha, 2) }})</strong> unggul pada
                <strong class="{{ $baselineAnalysis['winning_metrics_count'] === $baselineAnalysis['total_metrics'] ? 'text-success' : 'text-warning' }}">
                    {{ $baselineAnalysis['winning_metrics_count'] }} dari {{ $baselineAnalysis['total_metrics'] }} metrik
                </strong> dibandingkan rata-rata baseline pada K=10.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Dibandingkan dengan</th><th>Precision</th><th>Recall</th><th>NDCG</th><th>MAP</th></tr></thead>
                    <tbody>
                    @foreach($baselineAnalysis['comparisons'] as $c)
                        <tr>
                            <td>{{ $c['label'] }}</td>
                            @foreach(['precision', 'recall', 'ndcg', 'map'] as $metric)
                                <td class="fw-bold {{ $c['diffs'][$metric] > 0 ? 'text-success' : ($c['diffs'][$metric] < 0 ? 'text-danger' : 'text-muted') }}">
                                    {{ $c['diffs'][$metric] > 0 ? '+' : '' }}{{ number_format($c['diffs'][$metric], 1) }}%
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">Persentase = seberapa besar metrik Hybrid lebih tinggi/rendah dibanding strategi tersebut. Positif (hijau) berarti Hybrid lebih unggul.</p>
        </div>
    @endif
@endif

@if($latestAlphaSweepRuns->isNotEmpty())
    <div class="card-custom p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">📈 Grafik: Pencarian Alpha Optimal (batch terbaru -- K=10)</h6>
            <button class="btn btn-sm btn-outline-secondary no-print" onclick="downloadChartPng('chartAlphaSweep', 'alpha-sweep.png')">⬇️ Unduh PNG</button>
        </div>
        <p class="small text-muted">Batch: {{ $latestAlphaSweepLabel }} -- titik puncak tiap garis menunjukkan alpha dengan metrik terbaik.</p>
        <canvas id="chartAlphaSweep" height="90"></canvas>
    </div>

    @if($alphaSweepAnalysis)
        <div class="card-custom p-4 mb-4" style="border-left: 4px solid var(--primary);">
            <h6 class="fw-bold mb-2">📝 Kesimpulan: Alpha Optimal</h6>
            <p class="mb-0">
                Berdasarkan rata-rata ke-4 metrik (Precision, Recall, NDCG, MAP) pada K=10, nilai alpha dengan performa
                terbaik adalah <strong>α = {{ number_format($alphaSweepAnalysis['best_alpha'], 2) }}</strong>
                (rata-rata metrik {{ number_format($alphaSweepAnalysis['best_avg_metric'], 4) }}).
                @if($alphaSweepAnalysis['matches_production'])
                    Ini <strong class="text-success">sesuai</strong> dengan alpha yang dipakai di production (α = 0.6) --
                    pemilihan alpha production sudah didukung eksperimen.
                @else
                    Ini <strong class="text-warning">berbeda</strong> dari alpha yang dipakai di production (α = 0.6) --
                    pertimbangkan apakah perlu disesuaikan, atau catat sebagai temuan diskusi (mis. trade-off cold-start vs warm-start).
                @endif
            </p>
        </div>
    @endif
@endif

@forelse($batches as $label => $runs)
    <div class="card-custom p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Batch: {{ $label }}</h6>
            <span class="text-muted small">{{ $runs->first()->created_at->format('d M Y H:i') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Strategi</th><th>Alpha</th><th>K</th><th>Users Dievaluasi</th><th>Precision</th><th>Recall</th><th>NDCG</th><th>MAP</th></tr></thead>
                <tbody>
                @foreach($runs->sortBy(['k', 'alpha']) as $run)
                    <tr>
                        <td><span class="badge badge-note">{{ $run->strategy }}</span></td>
                        <td>{{ $run->alpha !== null ? number_format($run->alpha, 2) : '—' }}</td>
                        <td>{{ $run->k }}</td>
                        <td>{{ $run->users_evaluated }}</td>
                        <td>{{ number_format($run->precision, 4) }}</td>
                        <td>{{ number_format($run->recall, 4) }}</td>
                        <td>{{ number_format($run->ndcg, 4) }}</td>
                        <td>{{ number_format($run->map, 4) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card-custom p-5 text-center text-muted">
        Belum ada hasil evaluasi. Jalankan salah satu tombol di atas.
    </div>
@endforelse

<style>
    @media print {
        .no-print, .navbar-custom { display: none !important; }
        .card-custom { border: 1px solid #ccc !important; box-shadow: none !important; break-inside: avoid; }
    }
</style>

<script>
    // Data grafik disiapkan di server (pakai directive json Blade) supaya tidak perlu
    // endpoint AJAX terpisah -- batch evaluasi jarang berubah (cuma saat
    // tombol "Jalankan Evaluasi" ditekan), jadi render sekali saat load
    // halaman sudah cukup.
    const baselineRunsK10 = @json($latestBaselineRuns->where('k', 10)->sortBy('strategy')->values());
    const alphaSweepRunsK10 = @json($latestAlphaSweepRuns->where('k', 10)->sortBy('alpha')->values());

    const strategyLabels = { hybrid: 'Hybrid', cb_only: 'CB Murni', cf_only: 'CF Murni', popularity: 'Popularitas' };
    const metricColors = { precision: '#355DDB', recall: '#10B981', ndcg: '#F59E0B', map: '#F43F5E' };

    if (baselineRunsK10.length > 0) {
        new Chart(document.getElementById('chartBaseline'), {
            type: 'bar',
            data: {
                labels: baselineRunsK10.map(r => strategyLabels[r.strategy] ?? r.strategy),
                datasets: ['precision', 'recall', 'ndcg', 'map'].map(metric => ({
                    label: metric.toUpperCase(),
                    data: baselineRunsK10.map(r => parseFloat(r[metric])),
                    backgroundColor: metricColors[metric],
                })),
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 1 } },
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    if (alphaSweepRunsK10.length > 0) {
        new Chart(document.getElementById('chartAlphaSweep'), {
            type: 'line',
            data: {
                labels: alphaSweepRunsK10.map(r => 'α=' + parseFloat(r.alpha).toFixed(1)),
                datasets: ['precision', 'recall', 'ndcg', 'map'].map(metric => ({
                    label: metric.toUpperCase(),
                    data: alphaSweepRunsK10.map(r => parseFloat(r[metric])),
                    borderColor: metricColors[metric],
                    backgroundColor: metricColors[metric],
                    tension: 0.25,
                    fill: false,
                })),
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 1 } },
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    // Unduh grafik sebagai PNG -- pakai canvas.toDataURL bawaan Chart.js,
    // tanpa dependency tambahan (aman, tidak nambah risiko build).
    function downloadChartPng(canvasId, filename) {
        const canvas = document.getElementById(canvasId);
        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
    }
</script>
@endsection
