@extends('web.layouts.app')

@section('title', 'Booking Masuk -- Portal Pemilik')

@php
$statusLabel = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'];
$statusColor = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary', 'completed' => 'primary'];
@endphp

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="fw-bold mb-0">Booking Masuk</h4>
        <a href="{{ route('web.owner.dashboard') }}" class="btn btn-outline-koskita btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('web.owner.bookings.index') }}" class="btn btn-sm {{ !$status ? 'btn-primary-koskita' : 'btn-outline-koskita' }}">Semua</a>
        @foreach($statusLabel as $key => $label)
            <a href="{{ route('web.owner.bookings.index', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-primary-koskita' : 'btn-outline-koskita' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($bookings->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada booking</h6>
            <p class="text-muted small mb-0">Booking dari calon penyewa akan muncul di sini.</p>
        </div>
    @else
        {{-- Form terpisah dari form per-baris (status/pembayaran) di bawah --
             checkbox merujuk ke sini lewat atribut form="bulkPaidForm" (HTML5)
             supaya tidak perlu nested <form>, yang tidak valid HTML. --}}
        <form id="bulkPaidForm" action="{{ route('web.owner.bookings.bulk-paid') }}" method="POST" class="d-flex align-items-center gap-2 mb-3">
            @csrf
            <button type="submit" id="bulkPaidBtn" class="btn btn-sm btn-primary-koskita" disabled>
                <i class="bi bi-check2-all me-1"></i> Tandai Lunas yang Dipilih
            </button>
            <span id="bulkPaidCount" class="small text-muted"></span>
        </form>

        <div class="row g-3">
            @foreach($bookings as $b)
                <div class="col-12">
                    <div class="card-koskita p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-auto">
                                @if(!in_array($b->status, ['rejected', 'cancelled']) && $b->payment_status !== 'paid')
                                    <input type="checkbox" class="form-check-input bulk-paid-checkbox" name="booking_ids[]" value="{{ $b->id }}" form="bulkPaidForm" aria-label="Pilih booking {{ $b->kos->name ?? '' }} untuk ditandai lunas">
                                @endif
                            </div>
                            <div class="col-md-4">
                                <a href="{{ route('web.owner.koses.show', $b->kos_id) }}" class="fw-bold text-dark d-block mb-1">{{ $b->kos->name ?? '(kos dihapus)' }}</a>
                                <p class="small text-muted mb-0">
                                    {{ $b->user->name ?? '-' }} &middot; {{ $b->user->email ?? '-' }}
                                    @if($b->user)
                                        <a href="{{ route('web.messages.thread', $b->user->id) }}?kos_id={{ $b->kos_id }}" class="fw-semibold text-decoration-none ms-1">
                                            <i class="bi bi-chat-dots"></i> Chat
                                        </a>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-3">
                                <p class="small mb-0">Mulai {{ $b->start_date->translatedFormat('d M Y') }}</p>
                                <p class="small text-muted mb-0">{{ $b->duration_months }} bulan</p>
                                @if($b->notes)
                                    <p class="small text-muted mb-0 fst-italic">"{{ $b->notes }}"</p>
                                @endif
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-{{ $statusColor[$b->status] ?? 'secondary' }}-subtle text-{{ $statusColor[$b->status] ?? 'secondary' }}">{{ $statusLabel[$b->status] ?? $b->status }}</span>
                                @if(!in_array($b->status, ['rejected', 'cancelled']))
                                    <form action="{{ route('web.owner.bookings.payment', $b->id) }}" method="POST" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="payment_status" value="{{ $b->payment_status === 'paid' ? 'unpaid' : 'paid' }}">
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent d-flex align-items-center gap-1 {{ $b->payment_status === 'paid' ? 'text-success' : 'text-muted' }}" style="font-size: 0.78rem; font-weight: 700;">
                                            <i class="bi {{ $b->payment_status === 'paid' ? 'bi-check-circle-fill' : 'bi-hourglass-split' }}"></i>
                                            {{ $b->payment_status === 'paid' ? 'Sudah Dibayar' : 'Tandai Dibayar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div class="col-md-3 text-md-end">
                                @if($b->status === 'pending')
                                    <div class="d-flex gap-2 justify-content-md-end">
                                        <form action="{{ route('web.owner.bookings.status', $b->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Tolak</button>
                                        </form>
                                        <form action="{{ route('web.owner.bookings.status', $b->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-primary-koskita btn-sm">Konfirmasi</button>
                                        </form>
                                    </div>
                                @elseif($b->status === 'confirmed')
                                    <form action="{{ route('web.owner.bookings.status', $b->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-outline-koskita btn-sm">Tandai Selesai</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var checkboxes = document.querySelectorAll('.bulk-paid-checkbox');
        var btn = document.getElementById('bulkPaidBtn');
        var count = document.getElementById('bulkPaidCount');
        function update() {
            var checked = document.querySelectorAll('.bulk-paid-checkbox:checked').length;
            btn.disabled = checked === 0;
            count.textContent = checked > 0 ? checked + ' dipilih' : '';
        }
        checkboxes.forEach(function (cb) { cb.addEventListener('change', update); });
    });
</script>
@endpush
@endsection
