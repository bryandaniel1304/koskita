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
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm {{ !$status ? 'btn-primary-custom' : 'btn-outline-secondary' }}">Semua</a>
        @foreach($statusLabels as $key => $label)
            <a href="{{ route('admin.bookings.index', ['status' => $key]) }}" class="btn btn-sm {{ $status === $key ? 'btn-primary-custom' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
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
                        <td><small class="text-muted">{{ $b->created_at->diffForHumans() }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('admin.bookings.show', $b->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada booking.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-center">
    {{ $bookings->links('pagination::bootstrap-5') }}
</div>
@endsection
