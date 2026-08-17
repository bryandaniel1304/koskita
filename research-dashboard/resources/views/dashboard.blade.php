@extends('layouts.app')

@section('title', 'Ringkasan')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Ringkasan Data Skripsi</h4>
        <p class="text-muted mb-0">Semua angka di sini dihitung langsung dari database "koskita" (baca-saja) -- tidak ada data disintesis di dashboard ini.</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">🖨️ Cetak / Simpan sebagai PDF</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-custom p-3">
            <div class="stat-label">Total Responden</div>
            <div class="stat-value">{{ $totalRespondents }}</div>
            <div class="small text-muted">akun penyewa (role "user")</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3">
            <div class="stat-label">Warm-Start</div>
            <div class="stat-value">{{ $warmStartCount }}</div>
            <div class="small text-muted">sudah pernah kasih rating</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3">
            <div class="stat-label">Cold-Start</div>
            <div class="stat-value">{{ $coldStartCount }}</div>
            <div class="small text-muted">belum pernah rating apapun</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3">
            <div class="stat-label">Rata-rata Skor SUS</div>
            <div class="stat-value">{{ $avgSusScore ? number_format($avgSusScore, 1) : '—' }}</div>
            <div class="small text-muted">{{ $susCount }} responden mengisi</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-custom p-3">
            <h6 class="fw-bold mb-3">Sebaran Gender</h6>
            <canvas id="genderChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3">
            <h6 class="fw-bold mb-3">Sebaran Pekerjaan</h6>
            <canvas id="occupationChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3">
            <h6 class="fw-bold mb-3">Sebaran Area Kampus</h6>
            <canvas id="locationChart" height="180"></canvas>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-custom p-3">
            <h6 class="fw-bold mb-3">Cold-Start vs Warm-Start</h6>
            <canvas id="coldWarmChart" height="180"></canvas>
            <p class="small text-muted mb-0 mt-2">Warm-start = sudah pernah kasih rating (α efektif = 0.6). Cold-start = belum pernah rating (α efektif dipaksa 1.0, murni Content-Based -- lihat <code>SwitchingWeightedCombiner::effectiveAlpha()</code>).</p>
        </div>
    </div>
</div>

<div class="card-custom p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Hasil Evaluasi Terakhir</h6>
        <a href="{{ route('evaluation.index') }}" class="btn btn-sm btn-primary-custom">Lihat & Jalankan Evaluasi →</a>
    </div>
    @if($latestRuns->isEmpty())
        <p class="text-muted mb-0">Belum ada evaluasi yang dijalankan. Begitu data interaksi responden cukup (minimal 2 rating relevan per user), jalankan evaluasi di halaman "Evaluasi".</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Strategi</th><th>Alpha</th><th>K</th><th>Users</th><th>Precision</th><th>Recall</th><th>NDCG</th><th>MAP</th></tr></thead>
                <tbody>
                @foreach($latestRuns as $run)
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
    @endif
</div>

<script>
function pieChart(id, labels, data) {
    new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: ['#355DDB', '#7091F9', '#A5B4FC', '#CBD5E1', '#F43F5E'] }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}
pieChart('genderChart', {!! json_encode($genderBreakdown->keys()) !!}, {!! json_encode($genderBreakdown->values()) !!});
pieChart('occupationChart', {!! json_encode($occupationBreakdown->keys()) !!}, {!! json_encode($occupationBreakdown->values()) !!});
pieChart('locationChart', {!! json_encode($locationBreakdown->keys()) !!}, {!! json_encode($locationBreakdown->values()) !!});
pieChart('coldWarmChart', ['Warm-Start', 'Cold-Start'], [{{ $warmStartCount }}, {{ $coldStartCount }}]);
</script>
<style>
    @media print {
        .no-print, .navbar-custom { display: none !important; }
        .card-custom { border: 1px solid #ccc !important; box-shadow: none !important; break-inside: avoid; }
    }
</style>
@endsection
