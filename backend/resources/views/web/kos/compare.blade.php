@extends('web.layouts.app')

@section('title', 'Bandingkan Kos')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-columns-gap me-1"></i>Bandingkan Kos</h4>
        <a href="{{ route('web.kos.index') }}" class="btn btn-outline-koskita btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali Cari Kos
        </a>
    </div>

    <div class="table-responsive card-koskita p-2">
        <table class="table align-middle mb-0" style="min-width: {{ 180 + $koses->count() * 220 }}px;">
            <thead>
                <tr>
                    <th style="width: 160px;"></th>
                    @foreach($koses as $kos)
                        <th style="width: 220px;">
                            <a href="{{ route('web.kos.show', $kos->id) }}" class="text-decoration-none">
                                <img src="{{ $kos->cover_image }}" alt="{{ $kos->name }}" class="w-100 rounded-3 mb-2" style="height: 120px; object-fit: cover;">
                                <div class="fw-bold text-dark" style="font-size: 13px; line-height: 1.3;">{{ Str::limit($kos->name, 40) }}</div>
                            </a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="small fw-bold text-muted">Harga</td>
                    @foreach($koses as $kos)
                        <td class="fw-bold" style="color: var(--primary);">Rp {{ number_format($kos->price, 0, ',', '.') }}<span class="small text-muted fw-normal">/bln</span></td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted">Lokasi</td>
                    @foreach($koses as $kos)
                        <td class="small">{{ $kos->location }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted">Jarak Kampus</td>
                    @foreach($koses as $kos)
                        <td class="small">{{ $kos->distance_to_campus }} km</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted">Tipe</td>
                    @foreach($koses as $kos)
                        <td class="small text-uppercase">{{ $kos->gender_type }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted">Rating</td>
                    @foreach($koses as $kos)
                        <td class="small">
                            @if($kos->average_review_rating)
                                <i class="bi bi-star-fill text-warning"></i> {{ number_format($kos->average_review_rating, 1) }} ({{ $kos->reviews_count }})
                            @else
                                Belum ada
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted">Kamar Tersedia</td>
                    @foreach($koses as $kos)
                        <td class="small fw-bold {{ $kos->available_rooms > 0 ? 'text-success' : 'text-danger' }}">
                            {{ $kos->available_rooms > 0 ? $kos->available_rooms . ' kamar' : 'Penuh' }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td class="small fw-bold text-muted align-top">Fasilitas</td>
                    @foreach($koses as $kos)
                        <td class="align-top">
                            @if($kos->facilities->isEmpty())
                                <span class="small text-muted">-</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($kos->facilities as $facility)
                                        <span class="badge-soft" style="font-size: 10px;">{{ $facility->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td></td>
                    @foreach($koses as $kos)
                        <td>
                            <a href="{{ route('web.kos.show', $kos->id) }}" class="btn btn-primary-koskita btn-sm">Lihat Detail</a>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
