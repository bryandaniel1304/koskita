@extends('layouts.admin')

@section('title', 'Detail Booking')
@section('page_name', 'Detail Booking ' . $booking->code)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <span class="badge bg-dark-subtle text-dark font-monospace fs-6" title="Nomor pengajuan sewa">
        <i class="bi bi-hash"></i>{{ $booking->code }}
    </span>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card-custom h-100">
            <h6 class="fw-bold mb-3">Detail Pengajuan</h6>
            <table class="table table-sm mb-0">
                <tr><td class="text-muted">Nomor Pengajuan</td><td class="fw-semibold font-monospace">{{ $booking->code }}</td></tr>
                <tr><td class="text-muted">Pengguna</td><td class="fw-semibold">{{ $booking->user->name }} ({{ $booking->user->email }})</td></tr>
                <tr><td class="text-muted">Kos</td><td class="fw-semibold">{{ $booking->kos->name ?? '(kos dihapus)' }}</td></tr>
                <tr><td class="text-muted">Tanggal Mulai</td><td>{{ $booking->start_date->format('d M Y') }}</td></tr>
                <tr><td class="text-muted">Durasi</td><td>{{ $booking->duration_months }} bulan</td></tr>
                <tr><td class="text-muted">Catatan Pengguna</td><td>{{ $booking->notes ?: '-' }}</td></tr>
                <tr>
                    <td class="text-muted">Pembayaran</td>
                    <td>
                        @if(in_array($booking->status, ['rejected', 'cancelled']))
                            <span class="text-muted">&mdash;</span>
                        @elseif($booking->payment_status === 'paid')
                            <span class="badge bg-success-subtle text-success">Sudah Dibayar</span>
                            <small class="text-muted d-block mt-1">Ditandai {{ $booking->paid_at?->format('d M Y H:i') }} oleh pemilik kos</small>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Belum Dibayar</span>
                        @endif
                    </td>
                </tr>
                <tr><td class="text-muted">Diajukan</td><td>{{ $booking->created_at->format('d M Y H:i') }}</td></tr>
            </table>
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle"></i> Status pembayaran ditandai manual oleh pemilik kos melalui aplikasi
                (KosKita tidak memproses transaksi finansial apa pun).
            </p>
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

    <div class="col-12">
        <div class="card-custom">
            <h6 class="fw-bold mb-1">Riwayat Perubahan</h6>
            <p class="small text-muted mb-3">
                Setiap perubahan status pengajuan &amp; pembayaran dicatat otomatis beserta pelakunya --
                dari panel admin, dashboard pemilik kos, maupun pembatalan oleh penyewa sendiri.
            </p>

            <ul class="list-unstyled mb-0 booking-timeline">
                @foreach($booking->activityLogs as $log)
                    <li class="d-flex gap-3 pb-3">
                        <div class="text-center" style="width:28px;">
                            <i class="bi {{ $log->field === 'payment_status' ? 'bi-cash-coin' : 'bi-arrow-repeat' }} text-primary"></i>
                        </div>
                        <div class="flex-grow-1 border-bottom pb-3">
                            <div class="fw-semibold">
                                {{ $log->fieldLabel() }}:
                                @if($log->fromLabel())
                                    <span class="text-muted">{{ $log->fromLabel() }}</span>
                                    <i class="bi bi-arrow-right small mx-1"></i>
                                @endif
                                <span>{{ $log->toLabel() }}</span>
                            </div>
                            <div class="small text-muted">
                                oleh <span class="fw-semibold">{{ $log->actorLabel() }}</span>
                                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $log->actorRoleLabel() }}</span>
                                &middot; {{ $log->created_at->format('d M Y H:i') }}
                            </div>
                            @if($log->note)
                                <div class="small mt-1"><i class="bi bi-chat-left-text text-muted"></i> {{ $log->note }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach

                {{-- Titik awal timeline selalu dirender dari data booking-nya
                     sendiri (bukan baris log) -- supaya booking lama yang
                     dibuat sebelum fitur riwayat ini ada tetap punya awalan
                     yang benar, tanpa perlu mengarang baris riwayat palsu. --}}
                <li class="d-flex gap-3">
                    <div class="text-center" style="width:28px;">
                        <i class="bi bi-plus-circle text-success"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Pengajuan sewa dibuat</div>
                        <div class="small text-muted">
                            oleh <span class="fw-semibold">{{ $booking->user->name }}</span>
                            <span class="badge bg-secondary-subtle text-secondary ms-1">Penyewa</span>
                            &middot; {{ $booking->created_at->format('d M Y H:i') }}
                        </div>
                        @if($booking->notes)
                            <div class="small mt-1"><i class="bi bi-chat-left-text text-muted"></i> {{ $booking->notes }}</div>
                        @endif
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
