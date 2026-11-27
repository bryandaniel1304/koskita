@extends('web.layouts.app')

@section('title', $kos->name)
@section('meta_description', Str::limit(strip_tags($kos->description), 150))
@section('og_image', $kos->cover_image)
@section('og_type', 'product')

@push('structured-data')
{{-- Schema.org (Product+Offer) -- supaya Google bisa tampilkan harga/
     rating/lokasi langsung di hasil pencarian (rich snippet). JSON sudah
     di-encode di controller (lihat WebKosController::kosStructuredData)
     -- JANGAN pindah balik jadi json_encode([...]) inline di sini, literal
     '@context' di dalam file .blade.php kena tangkap compiler Blade
     sebagai directive @context dan merusak output. --}}
<script type="application/ld+json">{!! $kosStructuredData !!}</script>
@endpush

@section('content')
<div class="container py-4">
    <nav class="small text-muted mb-3">
        <a href="{{ route('web.home') }}" class="text-muted">Beranda</a> /
        <a href="{{ route('web.kos.index') }}" class="text-muted">Cari Kos</a> /
        <span>{{ $kos->name }}</span>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Galeri -- grid ala Airbnb (1 foto besar + beberapa thumbnail),
                 semua foto (bukan cuma yang tampil di grid) bisa dilihat lewat
                 lightbox begitu salah satu foto/tombol "Lihat semua" diklik. --}}
            @php
                $galleryImages = $kos->images->isNotEmpty() ? $kos->images->pluck('url')->all() : [$kos->cover_image];
                $galleryCount = count($galleryImages);
            @endphp
            <div class="position-relative mb-4">
                @if($galleryCount === 1)
                    <div class="card-koskita overflow-hidden">
                        <img src="{{ $galleryImages[0] }}" class="d-block w-100 img-shimmer gallery-open" data-index="0" style="height: 380px; object-fit: cover; cursor: pointer;" alt="{{ $kos->name }}">
                    </div>
                @else
                    <div class="gallery-grid">
                        <img src="{{ $galleryImages[0] }}" class="img-shimmer gallery-open gallery-grid-main" data-index="0" alt="{{ $kos->name }}">
                        <div class="gallery-grid-side">
                            @for($i = 1; $i <= min(4, $galleryCount - 1); $i++)
                                <div class="position-relative">
                                    <img src="{{ $galleryImages[$i] }}" class="img-shimmer gallery-open gallery-grid-thumb" data-index="{{ $i }}" alt="{{ $kos->name }}">
                                    @if($i === 4 && $galleryCount > 5)
                                        <div class="gallery-grid-more gallery-open" data-index="4">+{{ $galleryCount - 5 }} foto</div>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>
                    <button type="button" class="btn btn-light btn-sm shadow-sm gallery-open position-absolute d-flex align-items-center gap-2" data-index="0" style="bottom: 14px; right: 14px; border-radius: 10px; font-weight: 700;">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Lihat semua {{ $galleryCount }} foto
                    </button>
                @endif
            </div>

            <div class="card-koskita p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h3 class="fw-bold mb-1">
                            {{ $kos->name }}
                            @if($kos->verified_at)
                                <span class="badge bg-primary-subtle text-primary" title="Data & foto sudah dikonfirmasi admin"><i class="bi bi-patch-check-fill"></i> Terverifikasi</span>
                            @endif
                        </h3>
                        <p class="text-muted mb-0"><i class="bi bi-geo-alt-fill me-1"></i>{{ $kos->location }} &middot; {{ $kos->distance_to_campus }} km dari kampus</p>
                    </div>
                    <span class="badge-soft text-capitalize">Kos {{ $kos->gender_type }}</span>
                </div>

                <div class="d-flex flex-wrap gap-3 my-3">
                    @if($kos->average_review_rating)
                        <span class="fw-bold"><i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($kos->average_review_rating, 1) }} <span class="text-muted fw-normal">({{ $kos->reviews_count }} ulasan)</span></span>
                    @endif
                    @if($kos->owner_response_badge)
                        <span class="text-success small fw-semibold align-self-center"><i class="bi bi-lightning-charge-fill"></i> {{ $kos->owner_response_badge }}</span>
                    @endif
                    <span class="fw-bold {{ $kos->available_rooms > 0 ? '' : 'text-danger' }}">
                        <i class="bi bi-door-open-fill me-1"></i>
                        {{ $kos->available_rooms > 0 ? $kos->available_rooms . ' kamar tersedia' : 'Semua kamar penuh' }}
                        <span class="text-muted fw-normal">({{ $kos->occupied_rooms }}/{{ $kos->total_rooms }} terisi)</span>
                    </span>
                </div>

                @if($kos->roomTypes->isNotEmpty())
                    <hr>
                    <h6 class="fw-bold">Tipe Kamar</h6>
                    <div class="d-flex flex-column gap-2">
                        @foreach($kos->roomTypes as $type)
                            <div class="d-flex justify-content-between align-items-center p-2 rounded-3" style="background: var(--bg-light);">
                                <div>
                                    <div class="fw-bold small">{{ $type->name }}</div>
                                    <div class="text-muted small">{{ $type->total_rooms > 0 ? $type->total_rooms . ' kamar' : 'Penuh' }}</div>
                                </div>
                                <div class="fw-bold small" style="color: var(--primary);">Rp {{ number_format($type->price, 0, ',', '.') }}/bln</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($kos->description)
                    <hr>
                    <h6 class="fw-bold">Deskripsi</h6>
                    <p class="text-muted mb-0" style="white-space: pre-line;">{{ $kos->description }}</p>
                @endif

                @if($kos->facilities->isNotEmpty())
                    <hr>
                    <h6 class="fw-bold">Fasilitas</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($kos->facilities as $facility)
                            <span class="badge-soft"><i class="bi bi-check-circle-fill me-1"></i>{{ $facility->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if($kos->rules->isNotEmpty())
                    <hr>
                    <h6 class="fw-bold">Aturan</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($kos->rules as $rule)
                            <span class="badge-soft badge-neutral"><i class="bi bi-info-circle me-1"></i>{{ $rule->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Peta lokasi (OpenStreetMap -- gratis, tanpa API key, konsisten dengan yang dipakai app Flutter) --}}
            <div class="card-koskita p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-map me-1"></i>Lokasi</h6>
                <div id="petaKos" style="height: 280px; border-radius: 12px;"></div>
                @if(!($kos->latitude && $kos->longitude))
                    <p class="small text-muted mb-0 mt-2">Titik lokasi presisi belum diisi pemilik -- peta menunjukkan area {{ $kos->location }} secara umum.</p>
                @endif
            </div>

            {{-- Ulasan --}}
            <div class="card-koskita p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-1"></i>Ulasan Penyewa ({{ $kos->reviews->count() }})</h6>

                @auth
                    @if(!Auth::user()->hasVerifiedEmail())
                        <p class="small text-muted">Verifikasi email kamu dulu untuk bisa menulis ulasan.</p>
                    @elseif($canReview)
                        @error('review')
                            <div class="alert alert-danger border-0 shadow-sm small py-2">{{ $message }}</div>
                        @enderror
                        <form action="{{ route('web.kos.reviews.store', $kos->id) }}" method="POST" enctype="multipart/form-data" class="mb-4 p-3 js-loading-submit" style="background: var(--bg-light); border-radius: 12px;">
                            @csrf
                            <label class="small fw-bold text-muted mb-1 d-block">{{ $myReview ? 'Perbarui ulasan kamu' : 'Tulis ulasan kamu' }}</label>
                            <div class="mb-2">
                                @for ($i = 1; $i <= 5; $i++)
                                    <input type="radio" class="btn-check" name="rating" id="rating{{ $i }}" value="{{ $i }}" autocomplete="off" @checked(old('rating', $myReview->rating ?? 5) == $i)>
                                    <label class="btn btn-sm btn-outline-warning me-1" for="rating{{ $i }}">{{ $i }} <i class="bi bi-star-fill"></i></label>
                                @endfor
                            </div>
                            <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Ceritakan pengalamanmu tinggal di sini (opsional)">{{ old('comment', $myReview->comment ?? '') }}</textarea>
                            <div class="mb-2">
                                <label class="small text-muted mb-1 d-block">Lampirkan foto (opsional)</label>
                                <input type="file" name="photo" accept="image/*" class="form-control form-control-sm @error('photo') is-invalid @enderror">
                                @error('photo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @if($myReview?->photo_url)
                                    <img src="{{ $myReview->photo_url }}" alt="Foto ulasanmu saat ini" class="mt-2 rounded" style="width:64px;height:64px;object-fit:cover;">
                                @endif
                            </div>
                            <button type="submit" class="btn btn-primary-koskita btn-sm">{{ $myReview ? 'Perbarui Ulasan' : 'Kirim Ulasan' }}</button>
                        </form>
                    @else
                        {{-- Belum pernah menyewa (atau masih berjalan, belum "selesai")
                             -- tombol/form ulasan sengaja tidak ditampilkan sama sekali,
                             bukan cuma ditolak setelah submit. --}}
                        <p class="small text-muted mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Ulasan cuma bisa ditulis setelah masa sewamu di kos ini selesai. Sudah pernah tinggal di sini? Ulasanmu bisa ditulis begitu pemilik menandai booking-mu "Selesai".
                        </p>
                    @endif
                @endauth

                @forelse($kos->reviews as $review)
                    <div class="d-flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white" style="width:40px;height:40px;background:var(--primary);">
                            {{ strtoupper(substr($review->user->name ?? 'P', 0, 1)) }}
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <strong class="small">{{ $review->user->name ?? 'Pengguna' }}</strong>
                                <span class="text-warning small">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                    @endfor
                                </span>
                            </div>
                            @if($review->comment)
                                <p class="small text-muted mb-0 mt-1">{{ $review->comment }}</p>
                            @endif
                            @if($review->photo_url)
                                <a href="{{ $review->photo_url }}" target="_blank" rel="noopener">
                                    <img src="{{ $review->photo_url }}" alt="Foto dari ulasan {{ $review->user->name ?? 'penyewa' }}" class="mt-2 rounded" style="width:80px;height:80px;object-fit:cover;">
                                </a>
                            @endif
                            @if($review->owner_reply)
                                <div class="mt-2 p-2" style="background: var(--accent-soft, rgba(53,93,219,0.06)); border: 1px solid rgba(53,93,219,0.15); border-radius: 10px;">
                                    <p class="small fw-bold mb-1" style="color: var(--primary);"><i class="bi bi-shop me-1"></i>Balasan Pemilik</p>
                                    <p class="small text-muted mb-0">{{ $review->owner_reply }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Belum ada ulasan untuk kos ini.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-koskita p-4 sticky-top" style="top: 90px;">
                <p class="text-muted small mb-1">Mulai dari</p>
                <h3 class="fw-bold mb-3" style="color: var(--primary);">Rp {{ number_format($kos->price, 0, ',', '.') }}<span class="fs-6 text-muted fw-normal">/bulan</span></h3>

                @auth
                    @if($kos->available_rooms <= 0)
                        <button class="btn btn-outline-koskita w-100" disabled>Kos Sedang Penuh</button>
                        <form action="{{ route('web.kos.waitlist', $kos->id) }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-sm w-100 {{ $onWaitlist ? 'btn-outline-danger' : 'btn-outline-koskita' }}">
                                <i class="bi {{ $onWaitlist ? 'bi-bell-slash' : 'bi-bell' }} me-1"></i>
                                {{ $onWaitlist ? 'Batalkan Daftar Tunggu' : 'Beri Tahu Saya Saat Tersedia' }}
                            </button>
                        </form>
                    @elseif(!Auth::user()->hasVerifiedEmail())
                        <button class="btn btn-outline-koskita w-100" disabled>Verifikasi Email untuk Booking</button>
                    @else
                        <form action="{{ route('web.bookings.store', $kos->id) }}" method="POST" class="js-loading-submit">
                            @csrf
                            <div class="mb-2">
                                <label class="small fw-bold text-muted">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-muted">Durasi (bulan)</label>
                                <select name="duration_months" class="form-select form-select-sm" required>
                                    @foreach([1,3,6,12,24] as $m)
                                        <option value="{{ $m }}" @selected(old('duration_months') == $m)>{{ $m }} bulan</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-muted">Catatan (opsional)</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Mis. pertanyaan untuk pemilik">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary-koskita w-100">Ajukan Booking</button>
                        </form>
                    @endif
                    <form action="{{ route('web.kos.favorite', $kos->id) }}" method="POST" class="mt-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-koskita w-100 btn-sm">
                            <i class="bi {{ ($myInteraction && $myInteraction->is_favorite) ? 'bi-heart-fill text-danger' : 'bi-heart' }} me-1"></i>
                            {{ ($myInteraction && $myInteraction->is_favorite) ? 'Tersimpan di Favorit' : 'Simpan ke Favorit' }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('web.login') }}?redirect={{ urlencode(url()->current()) }}" class="btn btn-primary-koskita w-100">Masuk untuk Booking</a>
                    <p class="small text-muted text-center mt-2 mb-0">Belum punya akun? <a href="{{ route('web.register') }}">Daftar gratis</a></p>
                @endauth

                @if($confirmedBooking)
                    <hr>
                    <p class="small fw-bold mb-2"><i class="bi bi-receipt me-1"></i>Booking Dikonfirmasi</p>
                    @if($kos->owner && $kos->owner->qris_image_path)
                        <p class="small text-muted mb-2">Bayar sewa langsung ke pemilik lewat QRIS di bawah ini (KosKita tidak memproses pembayaran apa pun):</p>
                        <img src="{{ Storage::disk('public')->url($kos->owner->qris_image_path) }}" alt="QRIS {{ $kos->owner->name }}" class="img-fluid rounded-3 border mb-2" style="max-width: 220px;">
                    @endif
                    <a href="{{ route('web.bookings.receipt', $confirmedBooking->id) }}" class="small fw-semibold text-decoration-none d-inline-block" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text me-1"></i>Lihat & cetak bukti booking
                    </a>
                @endif

                @if($kos->owner)
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="small text-muted mb-0">
                            <i class="bi bi-person-badge me-1"></i>Dikelola oleh {{ $kos->owner->name }}
                            @if($kos->owner->owner_verification_status === 'approved')
                                <i class="bi bi-patch-check-fill text-primary" title="Pemilik Terverifikasi"></i>
                            @endif
                        </p>
                        @auth
                            @if(Auth::user()->role === 'user')
                                <a href="{{ route('web.messages.thread', $kos->owner->id) }}?kos_id={{ $kos->id }}" class="small fw-semibold text-decoration-none">
                                    <i class="bi bi-chat-dots me-1"></i>Chat
                                </a>
                            @endif
                        @endauth
                    </div>
                @endif

                @auth
                    <hr>
                    <button type="button" class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-flag me-1"></i>Laporkan kos ini
                    </button>
                @endauth
            </div>
        </div>
    </div>

    @if($similar->isNotEmpty())
        <div class="mt-5">
            <h5 class="fw-bold mb-3">Kos Lain di {{ $kos->location }}</h5>
            <div class="row g-4">
                @foreach($similar as $s)
                    <div class="col-md-4">
                        @include('web.partials.kos-card', ['kos' => $s])
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Lightbox galeri -- menampilkan SEMUA foto kos (bukan cuma yang tampil
         di grid), bisa navigasi lewat panah/klik thumbnail/keyboard. --}}
    <div class="gallery-lightbox" id="galleryLightbox">
        <div class="gallery-lightbox-top">
            <span class="gallery-lightbox-counter" id="galleryCounter"></span>
            <button type="button" class="gallery-lightbox-close" id="galleryClose" aria-label="Tutup galeri"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="gallery-lightbox-main">
            <button type="button" class="gallery-lightbox-nav gallery-lightbox-prev" id="galleryPrev" aria-label="Foto sebelumnya"><i class="bi bi-chevron-left"></i></button>
            <img id="galleryMainImage" src="" alt="{{ $kos->name }}">
            <button type="button" class="gallery-lightbox-nav gallery-lightbox-next" id="galleryNext" aria-label="Foto berikutnya"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="gallery-lightbox-thumbs" id="galleryThumbs"></div>
    </div>

    @auth
        <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 16px;">
                    <form action="{{ route('web.kos.report', $kos->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Laporkan "{{ $kos->name }}"</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="small fw-bold text-muted mb-1">Alasan</label>
                            <select name="reason" class="form-select mb-3" required>
                                <option value="">Pilih alasan...</option>
                                <option value="Foto/data tidak sesuai">Foto/data tidak sesuai kondisi asli</option>
                                <option value="Kos tidak ada/sudah tidak beroperasi">Kos tidak ada/sudah tidak beroperasi</option>
                                <option value="Harga tidak sesuai">Harga yang diminta tidak sesuai listing</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                            <label class="small fw-bold text-muted mb-1">Detail (opsional)</label>
                            <textarea name="details" class="form-control" rows="3" maxlength="1000"></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-koskita" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary-koskita">Kirim Laporan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    .gallery-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 6px;
        border-radius: 18px;
        overflow: hidden;
        height: 380px;
    }
    .gallery-grid-main { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
    .gallery-grid-side { display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; gap: 6px; }
    .gallery-grid-thumb { width: 100%; height: 100%; object-fit: cover; cursor: pointer; display: block; }
    .gallery-grid-more {
        position: absolute; inset: 0; background: rgba(15,23,42,0.55); color: #fff;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; cursor: pointer;
    }
    @media (max-width: 767px) {
        .gallery-grid { grid-template-columns: 1fr; height: auto; }
        .gallery-grid-main { height: 260px; border-radius: 18px 18px 0 0; }
        .gallery-grid-side { display: none; }
    }

    .gallery-lightbox {
        position: fixed; inset: 0; z-index: 10500; background: rgba(10, 12, 20, 0.97);
        display: none; flex-direction: column;
    }
    .gallery-lightbox.open { display: flex; }
    .gallery-lightbox-top { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; }
    .gallery-lightbox-counter { color: #fff; font-weight: 700; font-size: 0.9rem; background: rgba(255,255,255,0.12); padding: 5px 14px; border-radius: 20px; }
    .gallery-lightbox-close { background: rgba(255,255,255,0.12); border: none; color: #fff; width: 38px; height: 38px; border-radius: 50%; font-size: 1rem; }
    .gallery-lightbox-main { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; min-height: 0; padding: 0 16px; }
    .gallery-lightbox-main img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; }
    .gallery-lightbox-nav {
        position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.12); border: none; color: #fff;
        width: 44px; height: 44px; border-radius: 50%; font-size: 1.2rem; z-index: 2;
    }
    .gallery-lightbox-nav:hover { background: rgba(255,255,255,0.22); }
    .gallery-lightbox-prev { left: 16px; }
    .gallery-lightbox-next { right: 16px; }
    .gallery-lightbox-thumbs { display: flex; gap: 8px; overflow-x: auto; padding: 14px 18px; }
    .gallery-lightbox-thumbs img { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; cursor: pointer; opacity: 0.5; flex: 0 0 auto; border: 2px solid transparent; }
    .gallery-lightbox-thumbs img.active { opacity: 1; border-color: #fff; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    // Titik tengah area umum -- fallback kalau kos belum diisi koordinat
    // presisi, sama seperti KosLocationMap di app Flutter.
    const AREA_CENTERS = {
        karawaci: [-6.2088, 106.6003],
        bsd: [-6.3021, 106.6527],
        serpong: [-6.3208, 106.6714],
    };
    const lat = @json($kos->latitude);
    const lng = @json($kos->longitude);
    const locationKey = @json(strtolower(trim($kos->location)));
    const isPrecise = lat !== null && lng !== null;
    const point = isPrecise ? [lat, lng] : (AREA_CENTERS[locationKey] || AREA_CENTERS.karawaci);

    const map = L.map('petaKos').setView(point, isPrecise ? 16 : 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const marker = L.marker(point).addTo(map);
    marker.bindPopup(isPrecise ? @json($kos->name) : 'Area ' + @json($kos->location) + ' (lokasi perkiraan)');
</script>

<script>
    // Lightbox galeri -- daftar SEMUA foto (bukan cuma yang tampil di grid
    // 5-kotak), supaya calon penyewa bisa lihat semuanya lewat satu tempat.
    (function () {
        const images = @json($galleryImages);
        if (!images.length) return;

        const lightbox = document.getElementById('galleryLightbox');
        const mainImage = document.getElementById('galleryMainImage');
        const counter = document.getElementById('galleryCounter');
        const thumbsWrap = document.getElementById('galleryThumbs');
        let current = 0;

        thumbsWrap.innerHTML = images.map((src, i) => `<img src="${src}" data-index="${i}" alt="">`).join('');
        const thumbEls = thumbsWrap.querySelectorAll('img');

        function render() {
            mainImage.src = images[current];
            counter.textContent = (current + 1) + ' / ' + images.length;
            thumbEls.forEach((el, i) => el.classList.toggle('active', i === current));
            const activeThumb = thumbEls[current];
            if (activeThumb) activeThumb.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }

        function open(index) {
            current = index || 0;
            render();
            lightbox.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function close() {
            lightbox.classList.remove('open');
            document.body.style.overflow = '';
        }

        function prev() { current = (current - 1 + images.length) % images.length; render(); }
        function next() { current = (current + 1) % images.length; render(); }

        document.querySelectorAll('.gallery-open').forEach(el => {
            el.addEventListener('click', () => open(parseInt(el.dataset.index, 10) || 0));
        });
        thumbsWrap.addEventListener('click', (e) => {
            const img = e.target.closest('img');
            if (img) { current = parseInt(img.dataset.index, 10); render(); }
        });

        document.getElementById('galleryClose').addEventListener('click', close);
        document.getElementById('galleryPrev').addEventListener('click', prev);
        document.getElementById('galleryNext').addEventListener('click', next);
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox) close(); });
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') prev();
            if (e.key === 'ArrowRight') next();
        });
    })();
</script>

<script>
    // Catat kos ini ke riwayat "Terakhir Dilihat" (localStorage) begitu
    // halaman detail dibuka -- lihat public/js/recently-viewed.js.
    if (window.KoskitaRecent) {
        window.KoskitaRecent.track({
            id: @json($kos->id),
            name: @json($kos->name),
            location: @json($kos->location),
            price: @json($kos->price),
            image: @json($kos->cover_image),
        });
    }
</script>
@endpush
