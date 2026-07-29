@extends('layouts.admin')

@section('title', 'Detail Booking')
@section('page_name', 'Detail Booking #' . $booking->id)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Detail Pengajuan</h6>
            <table class="table table-sm mb-0">
                <tr><td class="text-muted">Pengguna</td><td class="fw-semibold">{{ $booking->user->name }} ({{ $booking->user->email }})</td></tr>
                <tr><td class="text-muted">Kos</td><td class="fw-semibold">{{ $booking->kos->name ?? '(kos dihapus)' }}</td></tr>
                <tr><td class="text-muted">Tanggal Mulai</td><td>{{ $booking->start_date->format('d M Y') }}</td></tr>
                <tr><td class="text-muted">Durasi</td><td>{{ $booking->duration_months }} bulan</td></tr>
                <tr><td class="text-muted">Catatan Pengguna</td><td>{{ $booking->notes ?: '-' }}</td></tr>
                <tr><td class="text-muted">Diajukan</td><td>{{ $booking->created_at->format('d M Y H:i') }}</td></tr>
            </table>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Kelola Status</h6>
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif
            <form action="{{ route('admin.bookings.update', $booking->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'completed' => 'Selesai'] as $key => $label)
                            <option value="{{ $key }}" {{ $booking->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Catatan Admin (opsional)</label>
                    <textarea name="admin_note" rows="3" class="form-control" placeholder="Mis. alasan penolakan, info kontak, dll.">{{ old('admin_note', $booking->admin_note) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary-custom">Simpan Perubahan</button>
            </form>
        </div>
    </div>
</div>
@endsection
