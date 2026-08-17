@extends('web.layouts.app')

@section('title', 'Beranda')

@section('content')
    <section style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);">
        <div class="container py-5">
            <div class="row align-items-center py-4">
                <div class="col-lg-7 text-white">
                    <span class="badge-soft mb-3 d-inline-block" style="background: rgba(255,255,255,0.18); color: #fff;">
                        <i class="bi bi-stars me-1"></i>Rekomendasi otomatis, bukan cuma daftar kos
                    </span>
                    <h1 class="fw-bold display-6 mb-3">Temukan kos yang paling cocok buatmu, bukan cuma yang ada.</h1>
                    <p class="mb-4" style="opacity: 0.92; max-width: 560px;">KosKita mencocokkan budget, preferensi fasilitas, dan gaya hidupmu dengan {{ $totalKos }}+ kos di Karawaci, BSD, dan Serpong -- lengkap dengan peta lokasi dan ulasan asli penyewa.</p>
                </div>
            </div>

            <div class="card-koskita p-3 p-md-4">
                <form action="{{ route('web.kos.index') }}" method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1">Cari nama / area kos</label>
                        <input type="text" name="search" class="form-control" placeholder="Mis. Kost Edelweiss / Karawaci">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Lokasi</label>
                        <select name="location" class="form-select">
                            <option value="">Semua lokasi</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc }}">{{ $loc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Tipe Kos</label>
                        <select name="gender_type" class="form-select">
                            <option value="">Putra &amp; Putri</option>
                            <option value="putra">Putra</option>
                            <option value="putri">Putri</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary-koskita">
                            <i class="bi bi-search me-1"></i>Cari Kos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- Statistik kepercayaan -- angka ASLI dari database, bukan testimoni
         karangan, ditampilkan sebagai bukti sosial begitu pengunjung
         mendarat di beranda. --}}
    <section class="container" style="margin-top: -28px; position: relative; z-index: 2;">
        <div class="card-koskita p-4">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <h3 class="fw-bold mb-0" style="color: var(--primary);">{{ $trustStats['total_kos'] }}+</h3>
                    <p class="small text-muted mb-0">Kos Terdaftar</p>
                </div>
                <div class="col-6 col-md-3">
                    <h3 class="fw-bold mb-0" style="color: var(--primary);">{{ $trustStats['total_users'] }}+</h3>
                    <p class="small text-muted mb-0">Penyewa Terdaftar</p>
                </div>
                <div class="col-6 col-md-3">
                    <h3 class="fw-bold mb-0" style="color: var(--primary);">{{ $trustStats['total_bookings_confirmed'] }}+</h3>
                    <p class="small text-muted mb-0">Booking Terkonfirmasi</p>
                </div>
                <div class="col-6 col-md-3">
                    <h3 class="fw-bold mb-0" style="color: var(--primary);"><i class="bi bi-patch-check-fill" style="font-size: 1.5rem;"></i> {{ $trustStats['verified_owners'] }}</h3>
                    <p class="small text-muted mb-0">Pemilik Terverifikasi</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:56px;height:56px;background:rgba(53,93,219,0.1);">
                    <i class="bi bi-sliders fs-4" style="color: var(--primary);"></i>
                </div>
                <h6 class="fw-bold">Isi Preferensimu</h6>
                <p class="small text-muted mb-0">Budget, tipe kos, dan fasilitas yang kamu butuhkan -- cukup sekali di awal.</p>
            </div>
            <div class="col-md-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:56px;height:56px;background:rgba(53,93,219,0.1);">
                    <i class="bi bi-stars fs-4" style="color: var(--primary);"></i>
                </div>
                <h6 class="fw-bold">Dapatkan Rekomendasi</h6>
                <p class="small text-muted mb-0">Sistem hybrid kami menghitung skor kecocokan tiap kos khusus untukmu.</p>
            </div>
            <div class="col-md-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:56px;height:56px;background:rgba(53,93,219,0.1);">
                    <i class="bi bi-calendar-check fs-4" style="color: var(--primary);"></i>
                </div>
                <h6 class="fw-bold">Ajukan Booking</h6>
                <p class="small text-muted mb-0">Langsung ajukan lewat situs, tanpa harus datang dulu ke lokasi kos.</p>
            </div>
        </div>
    </section>

    {{-- Riwayat "Terakhir Dilihat" -- disimpan di localStorage browser
         (lihat public/js/recently-viewed.js), jadi dirender lewat JS dan
         disembunyikan default (kosong sebelum ada riwayat sama sekali). --}}
    <section class="container py-2" id="recentlyViewedSection" style="display: none;">
        <h5 class="fw-bold mb-3">Terakhir Dilihat</h5>
        <div class="d-flex gap-3 pb-2" id="recentlyViewedRow" style="overflow-x: auto;"></div>
    </section>

    @if($featured->isNotEmpty())
        <section class="container py-4">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h4 class="fw-bold mb-1">Kos Unggulan</h4>
                    <p class="text-muted mb-0 small">Rating tertinggi dari penyewa yang sudah pernah tinggal.</p>
                </div>
                <a href="{{ route('web.kos.index') }}" class="fw-bold small" style="color: var(--primary);">Lihat Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-4">
                @foreach($featured as $kos)
                    <div class="col-md-6 col-lg-4">
                        @include('web.partials.kos-card', ['kos' => $kos])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container py-5">
        <div class="card-koskita p-4 p-md-5 text-center" style="background: var(--surface-dark); border: none;">
            <h4 class="fw-bold text-white mb-2">Punya kos dan mau dapat penyewa lebih cepat?</h4>
            <p class="text-white-50 mb-0" style="max-width: 620px; margin: 0 auto;">Kelola kos, kamar, dan booking lewat aplikasi KosKita untuk Pemilik -- lengkap dengan rekomendasi penyewa yang paling cocok dengan kos kamu.</p>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (function () {
        if (!window.KoskitaRecent) return;
        var items = window.KoskitaRecent.getAll();
        if (!items.length) return;

        var section = document.getElementById('recentlyViewedSection');
        var row = document.getElementById('recentlyViewedRow');

        function formatPrice(price) {
            return price >= 1000000
                ? 'Rp ' + (price / 1000000).toFixed(1) + ' jt/bln'
                : 'Rp ' + price + '/bln';
        }

        // Escape manual -- data kos (nama) berasal dari input pemilik kos,
        // dirender lewat innerHTML, jadi wajib di-escape supaya tidak ada
        // celah HTML/script ikut ter-render begitu saja.
        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = String(str == null ? '' : str);
            return div.innerHTML;
        }

        row.innerHTML = items.map(function (kos) {
            return '' +
                '<a href="/kos/' + encodeURIComponent(kos.id) + '" class="card-koskita text-decoration-none d-block flex-shrink-0" style="width: 200px; overflow: hidden;">' +
                '  <img src="' + escapeHtml(kos.image) + '" alt="" style="width: 100%; height: 100px; object-fit: cover;">' +
                '  <div class="p-2">' +
                '    <div class="fw-bold small text-truncate" style="color: var(--dark);">' + escapeHtml(kos.name) + '</div>' +
                '    <div class="small fw-bold mt-1" style="color: var(--primary);">' + formatPrice(kos.price) + '</div>' +
                '  </div>' +
                '</a>';
        }).join('');

        section.style.display = '';
    })();
</script>
@endpush
