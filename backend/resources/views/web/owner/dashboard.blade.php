@extends('web.layouts.app')

@section('title', 'Portal Pemilik')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1">Portal Pemilik</h4>
            <p class="text-muted small mb-0">Ringkasan kos & booking milikmu -- untuk kelola detail (tambah kos, unggah foto), pakai aplikasi KosKita.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('web.owner.analytics') }}" class="btn btn-outline-koskita">
                <i class="bi bi-graph-up me-1"></i> Analitik
            </a>
            <a href="{{ route('web.owner.bookings.index') }}" class="btn btn-primary-koskita">
                <i class="bi bi-calendar-check me-1"></i> Kelola Booking
            </a>
        </div>
    </div>

    <div class="card-koskita p-4 mb-3" style="background: linear-gradient(135deg, var(--primary), var(--primary-hover)); border: none;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <p class="text-white text-opacity-75 small mb-1">Pendapatan Bulan Ini</p>
                <h3 class="text-white fw-bold mb-0">Rp {{ number_format($stats['revenue_this_month'], 0, ',', '.') }}</h3>
                <p class="text-white text-opacity-75 small mb-0 mt-1">Dari booking yang sudah ditandai lunas -- bukan diproses KosKita.</p>
            </div>
            <i class="bi bi-graph-up-arrow text-white" style="font-size: 2.5rem; opacity: 0.5;"></i>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-houses-fill fs-4"></i></div>
                    <div>
                        <p class="text-muted mb-0 small">Kos Aktif</p>
                        <h4 class="mb-0 fw-bold">{{ $stats['total_kos'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-info bg-opacity-10 text-info rounded-3"><i class="bi bi-eye-fill fs-4"></i></div>
                    <div>
                        <p class="text-muted mb-0 small">Total Dilihat</p>
                        <h4 class="mb-0 fw-bold">{{ $stats['total_views'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('web.owner.bookings.index', ['status' => 'pending']) }}" class="text-decoration-none">
                <div class="card-koskita p-3 h-100" style="{{ $stats['pending_bookings'] > 0 ? 'border-color: #F59E0B;' : '' }}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-3"><i class="bi bi-calendar-exclamation fs-4"></i></div>
                        <div>
                            <p class="text-muted mb-0 small">Booking Menunggu</p>
                            <h4 class="mb-0 fw-bold">{{ $stats['pending_bookings'] }}</h4>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card-koskita p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded-3"><i class="bi bi-cash-coin fs-4"></i></div>
                    <div>
                        <p class="text-muted mb-0 small">Belum Ditandai Lunas</p>
                        <h4 class="mb-0 fw-bold">{{ $stats['unpaid_confirmed'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-koskita p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Kos Kamu</h6>
                    <a href="{{ route('web.owner.koses.index') }}" class="small fw-semibold">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                @forelse($koses as $kos)
                    <a href="{{ route('web.owner.koses.show', $kos->id) }}" class="d-flex align-items-center gap-3 text-decoration-none text-dark py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <img src="{{ $kos->cover_image }}" class="rounded-3" style="width: 52px; height: 52px; object-fit: cover;" alt="{{ $kos->name }}">
                        <div class="flex-grow-1">
                            <p class="fw-semibold mb-0 small">{{ $kos->name }}</p>
                            <p class="text-muted mb-0" style="font-size: 0.78rem;">{{ $kos->location }} &middot; {{ $kos->available_rooms }}/{{ $kos->total_rooms }} kamar kosong</p>
                        </div>
                        @if($kos->pending_bookings_count > 0)
                            <span class="badge bg-warning-subtle text-warning">{{ $kos->pending_bookings_count }} baru</span>
                        @endif
                    </a>
                @empty
                    <div class="text-center py-4">
                        <i class="bi bi-house-x d-block mb-2" style="font-size: 2rem; color: #CBD5E1;"></i>
                        <p class="text-muted small mb-0">Belum ada kos terdaftar. Tambahkan lewat aplikasi KosKita.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-koskita p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Booking Terbaru</h6>
                    <a href="{{ route('web.owner.bookings.index') }}" class="small fw-semibold">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                @php
                    $statusColor = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary', 'completed' => 'primary'];
                    $statusLabel = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'];
                @endphp
                @forelse($recentBookings as $b)
                    <div class="d-flex align-items-center gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="flex-grow-1">
                            <p class="fw-semibold mb-0 small">{{ $b->user->name ?? '-' }} &middot; {{ $b->kos->name ?? '(kos dihapus)' }}</p>
                            <p class="text-muted mb-0" style="font-size: 0.78rem;">{{ $b->start_date->translatedFormat('d M Y') }} &middot; {{ $b->duration_months }} bulan</p>
                        </div>
                        <span class="badge bg-{{ $statusColor[$b->status] ?? 'secondary' }}-subtle text-{{ $statusColor[$b->status] ?? 'secondary' }}">{{ $statusLabel[$b->status] ?? $b->status }}</span>
                    </div>
                @empty
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x d-block mb-2" style="font-size: 2rem; color: #CBD5E1;"></i>
                        <p class="text-muted small mb-0">Belum ada booking masuk.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
