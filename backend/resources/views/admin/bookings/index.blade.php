@extends('layouts.admin')

@section('title', 'Booking')
@section('page_name', 'Kelola Booking / Pengajuan Sewa')

@section('content')
@php
    $statusColors = [
        'pending' => 'warning',
        'confirmed' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'completed' => 'primary',
    ];
    $statusLabels = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'completed' => 'Selesai',
    ];
@endphp

<div class="card-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
            <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm {{ !$status ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Semua</a>
            @foreach($statusLabels as $key => $label)
                <a href="{{ route('admin.bookings.index', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-primary-custom' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <a href="{{ route('admin.bookings.export') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pengguna</th>
                    <th>Kos</th>
                    <th>Mulai</th>
                    <th>Durasi</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                    <th>Diajukan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                    <tr>
                        <td>{{ $b->user->name }}</td>
                        <td>{{ $b->kos->name ?? '(kos dihapus)' }}</td>
                        <td>{{ $b->start_date->format('d M Y') }}</td>
                        <td>{{ $b->duration_months }} bulan</td>
                        <td><span class="badge bg-{{ $statusColors[$b->status] }}-subtle text-{{ $statusColors[$b->status] }}">{{ $statusLabels[$b->status] }}</span></td>
                        <td>
                            @if(in_array($b->status, ['rejected', 'cancelled']))
                                <span class="text-muted">&mdash;</span>
                            @else
                                <span class="badge bg-{{ $b->payment_status === 'paid' ? 'success' : 'secondary' }}-subtle text-{{ $b->payment_status === 'paid' ? 'success' : 'secondary' }}">
                                    {{ $b->payment_status === 'paid' ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                                </span>
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $b->created_at->diffForHumans() }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('admin.bookings.show', $b->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                @empty
                    @include('admin.partials.empty-row', ['colspan' => 8, 'icon' => 'bi-calendar-x', 'text' => 'Belum ada booking.'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-center">
    {{ $bookings->links('pagination::bootstrap-5') }}
</div>
@endsection
