@extends('web.layouts.app')

@section('title', 'Rekomendasi Untukmu')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-stars me-1" style="color: var(--primary);"></i>Rekomendasi Untukmu</h4>
            <p class="text-muted small mb-0">
                @if($is_cold_start)
                    Kami sedang mempelajari preferensimu. Kos di bawah dicocokkan dari preferensi profil kamu -- beri rating kos yang kamu suka untuk hasil yang makin akurat.
                @else
                    Dihitung dari preferensi profil kamu dan pola penyewa lain dengan selera serupa ({{ $rating_count }} kos sudah kamu nilai).
                @endif
            </p>
        </div>
    </div>

    @if(empty($recommendations))
        <div class="card-koskita p-5 text-center mt-3">
            <i class="bi bi-emoji-neutral fs-1 text-muted mb-3"></i>
            <p class="text-muted mb-0">Belum ada kos yang bisa direkomendasikan saat ini.</p>
        </div>
    @else
        <div class="row g-4 mt-1">
            @foreach($recommendations as $item)
                @php $kos = $item['kos']; @endphp
                <div class="col-md-6">
                    <div class="card-koskita h-100 overflow-hidden">
                        <a href="{{ route('web.kos.show', $kos->id) }}" class="text-decoration-none text-dark d-flex">
                            <img src="{{ $kos->cover_image }}" alt="{{ $kos->name }}" style="width: 130px; min-width: 130px; height: 100%; object-fit: cover;">
                            <div class="p-3 flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <h6 class="fw-bold mb-1">{{ Str::limit($kos->name, 34) }}</h6>
                                    <span class="badge-soft flex-shrink-0">{{ $item['match_percentage'] }}% Cocok</span>
                                </div>
                                <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill me-1"></i>{{ $kos->location }}</p>
                                <p class="fw-bold small mb-2" style="color: var(--primary);">Rp {{ number_format($kos->price, 0, ',', '.') }}/bulan</p>
                            </div>
                        </a>
                        @if(!empty($item['explanation']))
                            <div class="px-3 pb-3">
                                <ul class="small text-muted mb-0 ps-3">
                                    @foreach(array_slice($item['explanation'], 0, 3) as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
