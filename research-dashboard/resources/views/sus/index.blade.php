@extends('layouts.app')

@section('title', 'Hasil SUS')

@section('content')
@php
    $sourceLabel = \App\Services\Sus\SusDataset::SOURCES[$source];
    $fmt = fn ($v, $d = 1) => $v === null ? '—' : number_format($v, $d);
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Hasil System Usability Scale</h4>
        <p class="text-muted mb-0">
            Dua sumber responden: kuesioner di <a href="{{ config('services.koskita.questionnaire_url') }}" target="_blank" rel="noopener">{{ config('services.koskita.questionnaire_url') }}</a> (dibaca langsung dari server KosKita)
            dan panel Populix (diimpor lewat <code>php artisan sus:import-populix</code>). Pernyataan dan rumus skornya sama.
        </p>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    @foreach(\App\Services\Sus\SusDataset::SOURCES as $key => $label)
        @php $n = $key === 'semua' ? $sourceCounts->sum() : ($sourceCounts[$key] ?? 0); @endphp
        <a href="{{ route('sus.index', array_filter(['sumber' => $key === 'semua' ? null : $key, 'kualitas' => $cleanOnly ? 'bersih' : null])) }}"
           class="btn btn-sm {{ $source === $key ? 'btn-primary-custom' : 'btn-outline-secondary' }}">{{ $label }} ({{ $n }})</a>
    @endforeach
    <span class="vr mx-1"></span>
    <a href="{{ route('sus.index', array_filter(['sumber' => $source === 'semua' ? null : $source])) }}"
       class="btn btn-sm {{ $cleanOnly ? 'btn-outline-secondary' : 'btn-primary-custom' }}">Semua jawaban</a>
    <a href="{{ route('sus.index', array_filter(['sumber' => $source === 'semua' ? null : $source, 'kualitas' => 'bersih'])) }}"
       class="btn btn-sm {{ $cleanOnly ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Tanpa jawaban bertanda</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-custom p-3 h-100">
            <div class="stat-label">Jumlah Pengisi</div>
            <div class="stat-value">{{ $count }}</div>
            <div class="small text-muted">{{ $sourceLabel }}{{ $cleanOnly ? ', tanpa jawaban bertanda' : '' }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3 h-100">
            <div class="stat-label">Rata-rata Skor SUS</div>
            <div class="stat-value">{{ $avgScore ? number_format($avgScore, 1) : '—' }} / 100</div>
            <div class="small text-muted">simpangan baku {{ $fmt($summary['sd'], 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3 h-100">
            <div class="stat-label">Median (Min–Maks)</div>
            <div class="stat-value">{{ $fmt($summary['median']) }}</div>
            <div class="small text-muted">{{ $fmt($summary['min']) }} – {{ $fmt($summary['max']) }}; {{ $summary['above_threshold'] }} responden ≥ 68</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-custom p-3 h-100">
            <div class="stat-label">Peringkat CGS (Sauro–Lewis)</div>
            <div class="stat-value" style="font-size: 20px;">{{ $avgScore ? \App\Models\SusResponse::interpret($avgScore).' · persentil '.\App\Models\SusResponse::percentileRange($avgScore) : '—' }}</div>
        </div>
    </div>
</div>

@if($sourceCounts->sum() > 0)
    <div class="row g-3 mb-4">
        <div class="{{ $demographics ? 'col-lg-7' : 'col-12' }}">
            <div class="card-custom p-3 h-100">
                <h6 class="fw-bold mb-2">Kualitas Jawaban ({{ $sourceLabel }})</h6>
                <ul class="small mb-2 ps-3">
                    <li><strong>{{ $flagCounts['straight_lining'] }}</strong> jawaban sama semua: kesepuluh pernyataan diberi angka yang sama, padahal arah pernyataan ganjil dan genap berlawanan.</li>
                    <li><strong>{{ $flagCounts['contradictory'] }}</strong> jawaban kontradiktif: setuju sistem mudah digunakan (Q3 ≥ 4) sekaligus setuju sistem rumit (Q2 ≥ 4) atau membingungkan (Q8 ≥ 4).</li>
                </ul>
                <p class="small mb-0">
                    Total <strong>{{ $flagCounts['flagged'] }}</strong> jawaban bertanda. Tanpa jawaban bertanda, tersisa
                    <strong>{{ $cleanSummary['count'] }}</strong> responden dengan rata-rata
                    <strong>{{ $fmt($cleanSummary['mean'], 2) }}</strong> (simpangan baku {{ $fmt($cleanSummary['sd'], 2) }}){{ $cleanSummary['mean'] ? ', peringkat CGS '.\App\Models\SusResponse::interpret($cleanSummary['mean']) : '' }}.
                    Penanda hanya informasi; keputusan mengecualikannya perlu dijelaskan di Bab IV.
                </p>
            </div>
        </div>
        @if($demographics)
            <div class="col-lg-5">
                <div class="card-custom p-3 h-100">
                    <h6 class="fw-bold mb-2">Karakteristik Responden Populix ({{ $demographics['count'] }})</h6>
                    <p class="small mb-1">
                        @foreach($demographics['gender'] as $gender => $n)
                            {{ $gender }}: <strong>{{ $n }}</strong> ({{ number_format($n / $demographics['count'] * 100, 0) }}%)@if(!$loop->last) · @endif
                        @endforeach
                    </p>
                    @if($demographics['age_mean'] !== null)
                        <p class="small mb-1">Usia {{ $demographics['age_min'] }}–{{ $demographics['age_max'] }} tahun, rata-rata {{ number_format($demographics['age_mean'], 1) }} tahun.</p>
                    @endif
                    <p class="small mb-0">
                        Domisili ({{ $demographics['provinces']->count() }} provinsi):
                        @foreach($demographics['provinces']->take(5) as $province => $n)
                            {{ $province }} {{ $n }}@if(!$loop->last), @endif
                        @endforeach
                        @if($demographics['provinces']->count() > 5), lainnya {{ $demographics['provinces']->slice(5)->sum() }}@endif.
                    </p>
                </div>
            </div>
        @endif
    </div>
@endif

@if($count > 0)
    {{-- Kesimpulan tertulis -- angka mentah butuh dimaknai, bukan cuma
         ditampilkan, supaya langsung bisa dikutip di Bab IV skripsi. --}}
    <div class="card-custom p-4 mb-4" style="border-left: 4px solid var(--primary);">
        <h6 class="fw-bold mb-2">📝 Kesimpulan Analisis SUS</h6>
        <p class="mb-2">
            Dari <strong>{{ $count }}</strong> responden ({{ strtolower($sourceLabel) }}{{ $cleanOnly ? ', tanpa jawaban bertanda' : '' }}), rata-rata skor SUS adalah <strong>{{ number_format($avgScore, 1) }}</strong>
            (simpangan baku {{ $fmt($summary['sd'], 2) }}; peringkat CGS <strong>{{ \App\Models\SusResponse::interpret($avgScore) }}</strong>, persentil {{ \App\Models\SusResponse::percentileRange($avgScore) }}), yang berarti
            @if($avgScore >= 68)
                <strong class="text-success">memenuhi ambang 68, yaitu rata-rata data acuan CGS Sauro–Lewis</strong> -- usabilitas aplikasi KosKita menurut responden berada di atas rata-rata.
            @else
                <strong class="text-danger">belum mencapai ambang 68, yaitu rata-rata data acuan CGS Sauro–Lewis</strong> -- usabilitas masih di bawah rata-rata dan perlu diperbaiki.
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
                <h6 class="fw-bold mb-1">Sebaran Peringkat CGS</h6>
                <p class="small text-muted">Jumlah responden per peringkat Curved Grading Scale Sauro–Lewis (A+ sampai F).</p>
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
            <thead><tr><th>Sumber</th><th>Responden</th><th>Profil</th><th>Jawaban Q1–Q10</th><th>Skor SUS</th><th>Interpretasi</th><th>Catatan</th><th>Diisi Pada</th></tr></thead>
            <tbody>
            @forelse($responses as $r)
                <tr>
                    <td><span class="badge {{ $r['source'] === 'populix' ? 'text-bg-warning' : 'badge-note' }}">{{ $r['source'] === 'populix' ? 'Populix' : 'KosKita' }}</span></td>
                    <td>{{ $r['label'] }}</td>
                    <td class="small text-muted">
                        @if($r['source'] === 'populix')
                            {{ collect([$r['gender'], $r['age'] ? $r['age'].' th' : null, $r['city']])->filter()->implode(' · ') }}
                        @else
                            {{ $r['email'] }}
                        @endif
                    </td>
                    <td class="text-nowrap"><code class="small text-body">{{ implode(' ', $r['answers']) }}</code></td>
                    <td class="fw-bold">{{ number_format($r['sus_score'], 1) }}</td>
                    <td>{{ \App\Models\SusResponse::interpret($r['sus_score']) }}</td>
                    <td>
                        @if($r['straight_lining'])<span class="badge text-bg-light border">Sama semua</span>@endif
                        @if($r['contradictory'])<span class="badge text-bg-light border">Kontradiktif</span>@endif
                    </td>
                    <td class="small">{{ $r['submitted_at']?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada responden yang mengisi.</td></tr>
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
        type: 'bar',
        data: {
            labels: @json(array_keys($gradeDistribution)),
            datasets: [{
                label: 'Responden',
                data: @json(array_values($gradeDistribution)),
                // A+..A- hijau, B+..B- biru, C+..C- kuning, D oranye, F merah.
                backgroundColor: ['#059669', '#059669', '#059669', '#355DDB', '#355DDB', '#355DDB', '#F59E0B', '#F59E0B', '#F59E0B', '#F97316', '#F43F5E'],
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
</script>
@endif
@endsection
