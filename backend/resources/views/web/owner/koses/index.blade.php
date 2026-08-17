@extends('web.layouts.app')

@section('title', 'Kos Kamu -- Portal Pemilik')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1">Kos Kamu</h4>
            <p class="text-muted small mb-0">Tambah kos baru & unggah foto lewat aplikasi KosKita.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('web.owner.koses.export') }}" class="btn btn-outline-koskita btn-sm">
                <i class="bi bi-download me-1"></i> Ekspor CSV
            </a>
            <button type="button" class="btn btn-outline-koskita btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload me-1"></i> Impor CSV
            </button>
            <a href="{{ route('web.owner.dashboard') }}" class="btn btn-outline-koskita btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 16px;">
                <form action="{{ route('web.owner.koses.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">Impor Harga &amp; Kamar Massal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Unggah file CSV hasil ekspor yang sudah kamu edit (kolom <code>id</code>, <code>harga</code>,
                            <code>total_kamar</code>) -- cocok untuk update harga/jumlah kamar banyak kos sekaligus,
                            lebih praktis daripada buka form satu-satu.
                        </p>
                        <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-koskita" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary-koskita">Unggah &amp; Terapkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($koses->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-house-x fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada kos terdaftar</h6>
            <p class="text-muted small mb-0">Tambahkan kos pertamamu lewat aplikasi KosKita untuk Pemilik.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach($koses as $kos)
                @php $s = $kosStats[$kos->id] ?? ['views' => 0, 'favorites' => 0, 'avg_rating' => null]; @endphp
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('web.owner.koses.show', $kos->id) }}" class="text-decoration-none text-dark">
                        <div class="card-koskita h-100 overflow-hidden">
                            <div class="position-relative">
                                <img src="{{ $kos->cover_image }}" class="w-100" style="height: 150px; object-fit: cover;" alt="{{ $kos->name }}">
                                @if($kos->pending_bookings_count > 0)
                                    <span class="badge bg-warning position-absolute top-0 end-0 m-2">{{ $kos->pending_bookings_count }} booking baru</span>
                                @endif
                            </div>
                            <div class="p-3">
                                <p class="fw-bold mb-1">{{ $kos->name }}</p>
                                <p class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> {{ $kos->location }}</p>
                                <div class="d-flex gap-3 small text-muted mb-2">
                                    <span><i class="bi bi-eye"></i> {{ $s['views'] }}</span>
                                    <span><i class="bi bi-heart"></i> {{ $s['favorites'] }}</span>
                                    @if($s['avg_rating'])
                                        <span><i class="bi bi-star-fill text-warning"></i> {{ $s['avg_rating'] }}</span>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-primary">Rp {{ number_format($kos->price, 0, ',', '.') }}</span>
                                    <span class="small {{ $kos->available_rooms > 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                        {{ $kos->available_rooms }}/{{ $kos->total_rooms }} kamar kosong
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
