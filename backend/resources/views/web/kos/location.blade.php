@extends('web.layouts.app')

@section('title', 'Kos di ' . $matchedLocation)
@section('meta_description', 'Cari kos di ' . $matchedLocation . ' -- ' . $stats['total'] . '+ pilihan kos mulai dari Rp ' . number_format($stats['min_price'], 0, ',', '.') . '/bulan, lengkap dengan rekomendasi otomatis, peta lokasi, dan ulasan penyewa asli.')

@section('content')
<div class="container py-4">
    <nav class="small text-muted mb-3">
        <a href="{{ route('web.home') }}" class="text-muted">Beranda</a> /
        <a href="{{ route('web.kos.index') }}" class="text-muted">Cari Kos</a> /
        <span>{{ $matchedLocation }}</span>
    </nav>

    <div class="mb-4">
        <h1 class="fw-bold" style="font-size: 1.85rem;">Kos di {{ $matchedLocation }}</h1>
        <p class="text-muted mb-0">
            {{ $stats['total'] }} kos terdaftar di area ini, mulai dari
            <strong>Rp {{ number_format($stats['min_price'], 0, ',', '.') }}/bulan</strong>
            (rata-rata Rp {{ number_format($stats['avg_price'], 0, ',', '.') }}/bulan).
            Semua listing lengkap dengan peta lokasi, ulasan penyewa asli, dan rekomendasi otomatis sesuai preferensimu.
        </p>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <p class="mb-0 text-muted small">Menampilkan <strong>{{ $koses->total() }}</strong> kos di {{ $matchedLocation }}</p>
        <form action="{{ route('web.kos.location', $location) }}" method="GET" class="d-flex align-items-center gap-2">
            <label class="small text-muted mb-0">Urutkan:</label>
            <select name="sort" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="terbaru" @selected($sort === 'terbaru')>Terbaru</option>
                <option value="harga_termurah" @selected($sort === 'harga_termurah')>Harga Termurah</option>
                <option value="harga_termahal" @selected($sort === 'harga_termahal')>Harga Tertinggi</option>
                <option value="jarak" @selected($sort === 'jarak')>Terdekat dari Kampus</option>
            </select>
        </form>
    </div>

    @if($koses->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-search fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada kos di area ini</h6>
        </div>
    @else
        <div class="row g-4">
            @foreach($koses as $kos)
                <div class="col-md-6 col-xl-4">
                    @include('web.partials.kos-card', ['kos' => $kos])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $koses->links() }}</div>
    @endif

    <div class="mt-5 pt-3 border-top">
        <p class="small text-muted mb-0">
            Cari di area lain: <a href="{{ route('web.kos.index') }}">lihat semua kos KosKita</a>.
        </p>
    </div>
</div>
@endsection
