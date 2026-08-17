@extends('web.layouts.app')

@section('title', 'Cari Kos')

@section('content')
<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card-koskita p-3 mb-4 sticky-top" style="top: 90px;">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-1"></i>Filter</h6>
                <form action="{{ route('web.kos.index') }}" method="GET">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Cari</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Nama / area kos">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Lokasi</label>
                        <select name="location" class="form-select form-select-sm">
                            <option value="">Semua lokasi</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc }}" @selected(request('location') === $loc)>{{ $loc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tipe Kos</label>
                        <select name="gender_type" class="form-select form-select-sm">
                            <option value="">Putra &amp; Putri</option>
                            <option value="putra" @selected(request('gender_type') === 'putra')>Putra</option>
                            <option value="putri" @selected(request('gender_type') === 'putri')>Putri</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Budget (Rp/bulan)</label>
                        <div class="d-flex gap-2">
                            <input type="number" name="budget_min" value="{{ request('budget_min') }}" class="form-control form-control-sm" placeholder="Min">
                            <input type="number" name="budget_max" value="{{ request('budget_max') }}" class="form-control form-control-sm" placeholder="Maks">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Fasilitas</label>
                        @foreach($facilities as $facility)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="facilities[]" value="{{ $facility->id }}"
                                    id="facility{{ $facility->id }}" @checked(in_array($facility->id, $selectedFacilities))>
                                <label class="form-check-label small" for="facility{{ $facility->id }}">{{ $facility->name }}</label>
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <button type="submit" class="btn btn-primary-koskita w-100 btn-sm">Terapkan Filter</button>
                    @if(request()->anyFilled(['search','location','gender_type','budget_min','budget_max','facilities']))
                        <a href="{{ route('web.kos.index') }}" class="btn btn-outline-koskita w-100 btn-sm mt-2">Reset</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <p class="mb-0 text-muted small">Menampilkan <strong>{{ $koses->total() }}</strong> kos</p>
                <form action="{{ route('web.kos.index') }}" method="GET" class="d-flex align-items-center gap-2">
                    @foreach(request()->except('sort') as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $v)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
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
                    <h6 class="fw-bold">Tidak ada kos yang cocok dengan filter ini</h6>
                    <p class="text-muted small mb-0">Coba longgarkan budget atau hapus beberapa filter.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach($koses as $kos)
                        <div class="col-md-6 col-xl-4">
                            <div class="position-relative">
                                <div class="form-check position-absolute d-flex align-items-center gap-1"
                                     style="top: 10px; left: 10px; z-index: 5; background: rgba(255,255,255,0.92); border-radius: 8px; padding: 4px 10px 4px 8px;">
                                    <input class="form-check-input compare-checkbox mt-0" type="checkbox" value="{{ $kos->id }}" id="cmp{{ $kos->id }}" style="cursor:pointer;">
                                    <label class="form-check-label small fw-bold mb-0" for="cmp{{ $kos->id }}" style="cursor:pointer; color:#0F172A;">Bandingkan</label>
                                </div>
                                @include('web.partials.kos-card', ['kos' => $kos])
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $koses->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Bar mengambang "Bandingkan" -- muncul begitu 2+ kos dicentang, lihat
     public/js/kos-compare.js. Sengaja HTML-nya selalu ada (bukan
     di-render server-side kondisional) supaya JS tinggal toggle class,
     tidak perlu reload halaman. --}}
<div id="compareBar" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 card-koskita px-4 py-3 d-none" style="z-index: 1050; box-shadow: 0 10px 30px -8px rgba(15,23,42,0.25);">
    <div class="d-flex align-items-center gap-3">
        <span class="small fw-bold" id="compareCount">0 kos dipilih</span>
        <a href="#" id="compareBtn" class="btn btn-primary-koskita btn-sm disabled">Bandingkan</a>
        <button type="button" class="btn btn-sm btn-outline-koskita" id="compareClear">Batal</button>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/kos-compare.js') }}"></script>
@endpush
@endsection
