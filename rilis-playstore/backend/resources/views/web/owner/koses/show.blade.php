@extends('web.layouts.app')

@section('title', $kos->name . ' -- Portal Pemilik')

@section('content')
<div class="container py-4">
    <a href="{{ route('web.owner.koses.index') }}" class="small text-muted d-inline-flex align-items-center gap-1 mb-3">
        <i class="bi bi-arrow-left"></i> Kembali ke Kos Kamu
    </a>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <img src="{{ $kos->cover_image }}" class="rounded-3" style="width: 72px; height: 72px; object-fit: cover;" alt="{{ $kos->name }}">
        <div>
            <h4 class="fw-bold mb-1">{{ $kos->name }}</h4>
            <p class="text-muted small mb-0"><i class="bi bi-geo-alt"></i> {{ $kos->location }} &middot; Rp {{ number_format($kos->price, 0, ',', '.') }}/bulan</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 text-center h-100">
                <p class="text-muted small mb-1">Dilihat</p>
                <h5 class="fw-bold mb-0">{{ $stats['total_views'] }}</h5>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 text-center h-100">
                <p class="text-muted small mb-1">Difavoritkan</p>
                <h5 class="fw-bold mb-0">{{ $stats['total_favorites'] }}</h5>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 text-center h-100">
                <p class="text-muted small mb-1">Rating Rata-rata</p>
                <h5 class="fw-bold mb-0">{{ $stats['avg_rating'] ?? '-' }}</h5>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 text-center h-100">
                <p class="text-muted small mb-1">Kamar Kosong</p>
                <h5 class="fw-bold mb-0">{{ $kos->available_rooms }}/{{ $kos->total_rooms }}</h5>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-koskita p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-check me-1"></i> Booking untuk Kos Ini</h6>
                @php
                    $statusColor = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary', 'completed' => 'primary'];
                    $statusLabel = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'];
                @endphp
                @forelse($bookings as $b)
                    <div class="py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <p class="fw-semibold mb-0 small">{{ $b->user->name ?? '-' }}</p>
                                <p class="text-muted mb-0" style="font-size: 0.78rem;">Mulai {{ $b->start_date->translatedFormat('d M Y') }} &middot; {{ $b->duration_months }} bulan</p>
                            </div>
                            <span class="badge bg-{{ $statusColor[$b->status] ?? 'secondary' }}-subtle text-{{ $statusColor[$b->status] ?? 'secondary' }}">{{ $statusLabel[$b->status] ?? $b->status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada booking untuk kos ini.</p>
                @endforelse
                <a href="{{ route('web.owner.bookings.index') }}" class="small fw-semibold d-inline-block mt-3">Kelola semua booking <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-koskita p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-1"></i> Ulasan Penyewa</h6>
                @forelse($kos->reviews as $review)
                    <div class="d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white" style="width:36px;height:36px;background:var(--primary);font-size:0.8rem;">
                            {{ strtoupper(substr($review->user->name ?? 'P', 0, 1)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <strong class="small">{{ $review->user->name ?? 'Pengguna' }}</strong>
                                <span class="text-warning small">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                    @endfor
                                </span>
                            </div>
                            @if($review->comment)
                                <p class="small text-muted mb-1 mt-1">{{ $review->comment }}</p>
                            @endif

                            @if($review->owner_reply)
                                <div class="mt-2 p-2" style="background: rgba(53,93,219,0.06); border: 1px solid rgba(53,93,219,0.15); border-radius: 10px;">
                                    <p class="small fw-bold mb-1" style="color: var(--primary);"><i class="bi bi-shop me-1"></i>Balasan Kamu</p>
                                    <p class="small text-muted mb-0">{{ $review->owner_reply }}</p>
                                </div>
                            @endif

                            <button class="btn btn-sm btn-link px-0 mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#replyForm{{ $review->id }}">
                                {{ $review->owner_reply ? 'Edit Balasan' : 'Balas Ulasan' }}
                            </button>
                            <div class="collapse mt-1" id="replyForm{{ $review->id }}">
                                <form action="{{ route('web.owner.koses.reviews.reply', [$kos->id, $review->id]) }}" method="POST">
                                    @csrf
                                    <textarea name="reply" rows="2" maxlength="1000" class="form-control form-control-sm mb-2" placeholder="Tulis balasan...">{{ $review->owner_reply }}</textarea>
                                    <button type="submit" class="btn btn-sm btn-primary-koskita">Simpan Balasan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Belum ada ulasan untuk kos ini.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
