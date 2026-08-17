{{-- Kartu kos yang dipakai berulang di beranda (unggulan) & katalog pencarian. --}}
<div class="card-koskita h-100 overflow-hidden">
    <a href="{{ route('web.kos.show', $kos->id) }}" class="text-decoration-none text-dark">
        <div class="position-relative">
            <img src="{{ $kos->cover_image }}" alt="{{ $kos->name }}" class="w-100 img-shimmer" style="height: 190px; object-fit: cover;">
            <span class="position-absolute top-0 end-0 m-2 badge-soft" style="background: rgba(15,23,42,0.75); color: #fff;">
                {{ $kos->gender_type }}
            </span>
            @if($kos->verified_at)
                <span class="position-absolute top-0 start-0 m-2 badge-soft" style="background: var(--primary); color: #fff;" title="Kos Terverifikasi">
                    <i class="bi bi-patch-check-fill"></i>
                </span>
            @endif
            @if($kos->available_rooms <= 0)
                <span class="position-absolute bottom-0 start-0 m-2 badge-soft" style="background: rgba(239,68,68,0.9); color: #fff;">Penuh</span>
            @endif
        </div>
        <div class="p-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h6 class="fw-bold mb-1" style="min-height: 2.6em;">{{ Str::limit($kos->name, 42) }}</h6>
            </div>
            <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill me-1"></i>{{ $kos->location }} &middot; {{ $kos->distance_to_campus }} km dari kampus</p>
            <div class="d-flex align-items-center gap-2 mb-2">
                @if($kos->average_review_rating)
                    <span class="small fw-bold text-dark"><i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($kos->average_review_rating, 1) }}</span>
                    <span class="small text-muted">({{ $kos->reviews_count }} ulasan)</span>
                @else
                    <span class="small text-muted">Belum ada ulasan</span>
                @endif
            </div>
            @if($kos->owner_response_badge ?? null)
                <p class="small text-success fw-semibold mb-2"><i class="bi bi-lightning-charge-fill"></i> {{ $kos->owner_response_badge }}</p>
            @endif
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold" style="color: var(--primary);">Rp {{ number_format($kos->price, 0, ',', '.') }}<span class="small text-muted fw-normal">/bulan</span></span>
                <span class="small text-muted">{{ $kos->available_rooms }} kamar tersisa</span>
            </div>
        </div>
    </a>
</div>
