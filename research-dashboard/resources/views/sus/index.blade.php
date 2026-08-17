@extends('layouts.app')

@section('title', 'Hasil SUS')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Hasil System Usability Scale</h4>
        <p class="text-muted mb-0">Bagikan link <a href="{{ route('sus.create') }}" target="_blank">{{ route('sus.create') }}</a> ke 150 responden setelah mereka mencoba app.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-custom p-3">
            <div class="stat-label">Jumlah Pengisi</div>
            <div class="stat-value">{{ $count }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3">
            <div class="stat-label">Rata-rata Skor SUS</div>
            <div class="stat-value">{{ $avgScore ? number_format($avgScore, 1) : '—' }} / 100</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3">
            <div class="stat-label">Interpretasi (Bangor et al.)</div>
            <div class="stat-value" style="font-size: 20px;">{{ $avgScore ? \App\Models\SusResponse::interpret($avgScore) : '—' }}</div>
        </div>
    </div>
</div>

@if($count > 0)
    {{-- Kesimpulan tertulis -- angka mentah butuh dimaknai, bukan cuma
         ditampilkan, supaya langsung bisa dikutip di Bab IV skripsi. --}}
    <div class="card-custom p-4 mb-4" style="border-left: 4px solid var(--primary);">
        <h6 class="fw-bold mb-2">📝 Kesimpulan Analisis SUS</h6>
        <p class="mb-2">
            Dari <strong>{{ $count }}</strong> responden, rata-rata skor SUS adalah <strong>{{ number_format($avgScore, 1) }}</strong>
            (kategori <strong>{{ \App\Models\SusResponse::interpret($avgScore) }}</strong>), yang berarti
            @if($avgScore >= 68)
                <strong class="text-success">berada di atas rata-rata acuan industri SUS (68.0)</strong> -- usabilitas aplikasi KosKita menurut responden tergolong baik.
            @else
                <strong class="text-danger">berada di bawah rata-rata acuan industri SUS (68.0)</strong> -- masih ada ruang perbaikan usabilitas sebelum dianggap setara standar industri.
            @endif
        </p>
        @if($strongestQuestion && $weakestQuestion)
            <p class="mb-0">
                Aspek yang dinilai <strong class="text-success">paling positif</strong> oleh responden: <em>"{{ $strongestQuestion['text'] }}"</em>
                (skor kontribusi {{ number_format($strongestQuestion['avg_contribution'], 2) }}/4.00).
                Aspek yang <strong class="text-danger">paling perlu diperbaiki</strong>: <em>"{{ $weakestQuestion['text'] }}"</em>
                (skor kontribusi {{ number_format($weakestQuestion['avg_contribution'], 2) }}/4.00).
            </p>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card-custom p-3">
                <h6 class="fw-bold mb-1">Skor Rata-rata per Pertanyaan</h6>
                <p class="small text-muted">Skala 0-4, sudah dinormalisasi (pertanyaan bernada negatif dibalik) supaya makin tinggi selalu berarti makin positif -- bisa dibandingkan langsung antar pertanyaan.</p>
                <canvas id="chartPerQuestion" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card-custom p-3">
                <h6 class="fw-bold mb-1">Sebaran Kategori Interpretasi</h6>
                <p class="small text-muted">Berapa persen responden masuk tiap kategori Bangor et al.</p>
                <canvas id="chartGradeDistribution" height="220"></canvas>
            </div>
        </div>
    </div>

    <div class="card-custom p-3 mb-4">
        <h6 class="fw-bold mb-3">Detail per Pertanyaan</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>Pernyataan</th><th>Rata-rata Mentah (1-5)</th><th>Skor Kontribusi (0-4)</th></tr></thead>
                <tbody>
                @foreach($questionBreakdown as $q)
                    <tr class="{{ $q['question'] === $weakestQuestion['question'] ? 'table-danger' : ($q['question'] === $strongestQuestion['question'] ? 'table-success' : '') }}">
                        <td>Q{{ $q['question'] }}</td>
                        <td class="small">{{ $q['text'] }}</td>
                        <td>{{ number_format($q['avg_raw'], 2) }}</td>
                        <td class="fw-bold">{{ number_format($q['avg_contribution'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card-custom p-3">
    <h6 class="fw-bold mb-3">Data Mentah per Responden</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Responden</th><th>Skor SUS</th><th>Interpretasi</th><th>Diisi Pada</th></tr></thead>
            <tbody>
            @forelse($responses as $r)
                <tr>
                    <td>{{ $r->respondent_name ?: 'Anonim' }}</td>
                    <td class="fw-bold">{{ number_format($r->sus_score, 1) }}</td>
                    <td>{{ \App\Models\SusResponse::interpret($r->sus_score) }}</td>
                    <td>{{ $r->created_at->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada responden yang mengisi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $responses->links() }}</div>
</div>

@if($count > 0)
<script>
    new Chart(document.getElementById('chartPerQuestion'), {
        type: 'bar',
        data: {
            labels: @json(collect($questionBreakdown)->pluck('question')->map(fn($n) => 'Q' . $n)),
            datasets: [{
                label: 'Skor Kontribusi (0-4)',
                data: @json(collect($questionBreakdown)->pluck('avg_contribution')),
                backgroundColor: @json(collect($questionBreakdown)->pluck('avg_contribution')->map(fn($v) => $v >= 2.5 ? '#10B981' : ($v >= 1.5 ? '#F59E0B' : '#F43F5E'))),
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, max: 4 } },
        },
    });

    new Chart(document.getElementById('chartGradeDistribution'), {
        type: 'doughnut',
        data: {
            labels: @json(array_keys($gradeDistribution)),
            datasets: [{
                data: @json(array_values($gradeDistribution)),
                backgroundColor: ['#059669', '#10B981', '#355DDB', '#F59E0B', '#F97316', '#F43F5E'],
            }],
        },
        options: { plugins: { legend: { position: 'bottom' } } },
    });
</script>
@endif
@endsection
