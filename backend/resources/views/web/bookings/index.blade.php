@extends('web.layouts.app')

@section('title', 'Booking Saya')

@php
$statusLabel = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'];
$statusColor = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary', 'completed' => 'primary'];
@endphp

@section('content')
<div class="container py-4">
    <h4 class="fw-bold mb-4">Booking Saya</h4>

    @if($bookings->isEmpty())
        <div class="card-koskita p-5 text-center">
            <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
            <h6 class="fw-bold">Belum ada booking</h6>
            <p class="text-muted small mb-3">Cari kos yang cocok dan ajukan booking pertamamu.</p>
            <a href="{{ route('web.kos.index') }}" class="btn btn-primary-koskita mx-auto" style="width: fit-content;">Cari Kos</a>
        </div>
    @else
        <div class="row g-3">
            @foreach($bookings as $booking)
                <div class="col-12">
                    <div class="card-koskita p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <div class="col-md-2">
                                <img src="{{ $booking->kos->cover_image }}" class="w-100 rounded-3" style="height: 80px; object-fit: cover;" alt="{{ $booking->kos->name }}">
                            </div>
                            <div class="col-md-6">
                                <a href="{{ route('web.kos.show', $booking->kos_id) }}" class="fw-bold text-dark d-block mb-1">{{ $booking->kos->name }}</a>
                                <p class="small text-muted mb-0">
                                    Mulai {{ $booking->start_date->translatedFormat('d M Y') }} &middot; {{ $booking->duration_months }} bulan
                                </p>
                                @if($booking->notes)
                                    <p class="small text-muted mb-0">Catatan: {{ $booking->notes }}</p>
                                @endif
                                @if($booking->admin_note)
                                    <p class="small text-muted mb-0 fst-italic">Catatan pemilik: {{ $booking->admin_note }}</p>
                                @endif
                                @if(!in_array($booking->status, ['rejected', 'cancelled']))
                                    <p class="small mb-0 mt-1 {{ $booking->payment_status === 'paid' ? 'text-success fw-semibold' : 'text-muted' }}">
                                        <i class="bi {{ $booking->payment_status === 'paid' ? 'bi-check-circle-fill' : 'bi-hourglass-split' }}"></i>
                                        {{ $booking->payment_status === 'paid' ? 'Sudah dibayar' : 'Belum dibayar ke pemilik' }}
                                    </p>
                                @endif
                                @if($booking->kos->owner_id)
                                    <a href="{{ route('web.messages.thread', $booking->kos->owner_id) }}?kos_id={{ $booking->kos_id }}" class="small fw-semibold text-decoration-none d-inline-block mt-1">
                                        <i class="bi bi-chat-dots"></i> Chat Pemilik
                                    </a>
                                @endif
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-{{ $statusColor[$booking->status] ?? 'secondary' }}">{{ $statusLabel[$booking->status] ?? $booking->status }}</span>
                            </div>
                            <div class="col-md-2 text-md-end">
                                @if(in_array($booking->status, ['pending', 'confirmed']))
                                    <form action="{{ route('web.bookings.cancel', $booking->id) }}" method="POST" onsubmit="return confirm('Batalkan booking ini?')">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-koskita btn-sm w-100">Batalkan</button>
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
@endsection
