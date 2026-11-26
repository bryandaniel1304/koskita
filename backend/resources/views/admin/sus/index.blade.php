@extends('layouts.admin')

@section('title', 'Kuesioner SUS')
@section('page_name', 'Hasil Kuesioner SUS')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <p class="text-muted small mb-0">
        Jawaban System Usability Scale dari responden uji coba online. Form publik:
        <a href="{{ route('sus.create') }}" target="_blank" rel="noopener">{{ route('sus.create') }}</a>
    </p>
    <a href="{{ route('admin.sus.export') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <i class="bi bi-download"></i> Ekspor CSV
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-people-fill fs-3"></i></div>
            <div>
                <p class="text-muted mb-0 small">Jumlah Responden</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $count }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3"><i class="bi bi-speedometer fs-3"></i></div>
            <div>
                <p class="text-muted mb-0 small">Rata-rata Skor SUS</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $avgScore !== null ? number_format($avgScore, 2, ',', '.') : '-' }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom d-flex align-items-center gap-3">
            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3"><i class="bi bi-award fs-3"></i></div>
            <div>
                <p class="text-muted mb-0 small">Peringkat CGS (Sauro–Lewis)</p>
                <h3 class="mb-0 fw-bold text-dark">{{ $avgScore !== null ? \App\Models\SusResponse::interpret($avgScore) : '-' }}</h3>
                @if($avgScore !== null)
                    <p class="text-muted mb-0 small">Persentil {{ \App\Models\SusResponse::percentileRange($avgScore) }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-1">Rata-rata per Pernyataan</h6>
            <p class="text-muted small mb-3">Kontribusi 0-4 (pernyataan genap sudah dibalik), makin tinggi makin baik. Dipakai untuk melihat aspek terlemah/terkuat.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Pernyataan</th><th class="text-end">Rata-rata (1-5)</th><th class="text-end">Kontribusi (0-4)</th></tr>
                    </thead>
                    <tbody>
                        @foreach($questionBreakdown as $row)
                            <tr>
                                <td class="small text-muted">Q{{ $row['question'] }}</td>
                                <td class="small">{{ $row['text'] }}</td>
                                <td class="small text-end">{{ $count ? number_format($row['avg_raw'], 2, ',', '.') : '-' }}</td>
                                <td class="small text-end fw-semibold">{{ $count ? number_format($row['avg_contribution'], 2, ',', '.') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Sebaran Peringkat CGS</h6>
            @foreach($gradeDistribution as $grade => $total)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small"><span>{{ $grade }}</span><span class="text-muted">{{ $total }}</span></div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: {{ $count ? round($total / $count * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@php($canEdit = auth()->user()->isMasterAdmin())
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<div class="card-custom">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">Jawaban Responden</h6>
        @if($canEdit)
            <button type="submit" form="sus-bulk-delete" class="btn btn-outline-danger btn-sm" id="sus-bulk-btn" disabled>
                <i class="bi bi-trash"></i> Hapus yang dipilih (<span id="sus-bulk-count">0</span>)
            </button>
        @endif
    </div>
    @if($canEdit)
        <form id="sus-bulk-delete" action="{{ route('admin.sus.bulk-destroy') }}" method="POST"
              onsubmit="return confirm('Hapus semua jawaban yang dipilih? Tindakan ini tidak bisa dibatalkan.');">
            @csrf
            @method('DELETE')
        </form>
    @endif
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    @if($canEdit)
                        <th><input type="checkbox" class="form-check-input" id="sus-check-all" aria-label="Pilih semua"></th>
                    @endif
                    <th>Responden</th>
                    @foreach(array_keys(\App\Models\SusResponse::QUESTIONS) as $num)
                        <th class="text-center">Q{{ $num }}</th>
                    @endforeach
                    <th class="text-end">Skor</th>
                    <th>Kesan & Saran</th>
                    <th>Waktu</th>
                    @if($canEdit)
                        <th class="text-end">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($responses as $response)
                    <tr>
                        @if($canEdit)
                            <td><input type="checkbox" class="form-check-input sus-check" name="ids[]" value="{{ $response->id }}" form="sus-bulk-delete" aria-label="Pilih {{ $response->respondent_name }}"></td>
                        @endif
                        <td class="small">
                            <div class="fw-semibold">{{ $response->respondent_name }}</div>
                            <div class="text-muted">{{ $response->respondent_email }}</div>
                        </td>
                        @foreach(array_keys(\App\Models\SusResponse::QUESTIONS) as $num)
                            <td class="small text-center">{{ $response->{"q$num"} }}</td>
                        @endforeach
                        <td class="small text-end fw-bold">{{ number_format($response->sus_score, 1, ',', '.') }}</td>
                        <td class="small" style="min-width: 220px;">{{ $response->feedback ?: '-' }}</td>
                        <td class="small text-muted text-nowrap">{{ $response->created_at->format('d M Y H:i') }}</td>
                        @if($canEdit)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.sus.edit', $response->id) }}" class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <button type="submit" form="sus-delete-{{ $response->id }}" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </td>
                        @endif
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => $canEdit ? 16 : 14, 'icon' => 'bi-clipboard-check', 'text' => 'Belum ada responden yang mengisi kuesioner.'])
                @endforelse
            </tbody>
        </table>
    </div>
    @if($canEdit)
        @foreach($responses as $response)
            <form id="sus-delete-{{ $response->id }}" action="{{ route('admin.sus.destroy', $response->id) }}" method="POST" class="d-none"
                  onsubmit="return confirm('Hapus jawaban responden ini? Tindakan ini tidak bisa dibatalkan.');">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
        <script>
            (function () {
                const all = document.getElementById('sus-check-all');
                const boxes = () => document.querySelectorAll('.sus-check');
                const btn = document.getElementById('sus-bulk-btn');
                const count = document.getElementById('sus-bulk-count');
                const sync = () => {
                    const n = [...boxes()].filter(b => b.checked).length;
                    count.textContent = n;
                    btn.disabled = n === 0;
                };
                all?.addEventListener('change', () => { boxes().forEach(b => b.checked = all.checked); sync(); });
                boxes().forEach(b => b.addEventListener('change', sync));
            })();
        </script>
    @endif
    <div class="mt-3">{{ $responses->links() }}</div>
</div>
@endsection
