@extends('web.layouts.app')

@section('title', 'Pengaturan -- Portal Pemilik')

@php
$verifLabel = ['none' => 'Belum Mengajukan', 'pending' => 'Menunggu Peninjauan Admin', 'approved' => 'Terverifikasi', 'rejected' => 'Ditolak, Silakan Kirim Ulang'];
$verifColor = ['none' => 'secondary', 'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
@endphp

@section('content')
<div class="container py-4" style="max-width: 760px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="fw-bold mb-0">Pengaturan Akun Pemilik</h4>
        <a href="{{ route('web.owner.dashboard') }}" class="btn btn-outline-koskita btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="mb-4">
        @include('web.partials.avatar-settings')
    </div>

    <div class="card-koskita p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h6 class="fw-bold mb-1"><i class="bi bi-patch-check me-1"></i> Verifikasi Pemilik</h6>
                <p class="text-muted small mb-0">Kirim foto KTP atau dokumen kepemilikan supaya kos kamu dapat badge "Pemilik Terverifikasi" -- meningkatkan kepercayaan calon penyewa.</p>
            </div>
            <span class="badge bg-{{ $verifColor[$owner->owner_verification_status] ?? 'secondary' }}-subtle text-{{ $verifColor[$owner->owner_verification_status] ?? 'secondary' }} flex-shrink-0">
                {{ $verifLabel[$owner->owner_verification_status] ?? $owner->owner_verification_status }}
            </span>
        </div>

        @if($owner->owner_verification_document)
            <img src="{{ Storage::disk('public')->url($owner->owner_verification_document) }}" alt="Dokumen terkirim" style="max-width: 200px;" class="rounded-3 border mb-3">
        @endif

        @unless($owner->isVerifiedOwner())
            <form action="{{ route('web.owner.verification.submit') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                @csrf
                <input type="file" name="document" accept="image/*" class="form-control" style="max-width: 320px;" required>
                <button type="submit" class="btn btn-primary-koskita">Kirim untuk Ditinjau</button>
            </form>
        @endunless
    </div>

    <div class="card-koskita p-4">
        <h6 class="fw-bold mb-1"><i class="bi bi-qr-code me-1"></i> Kode QRIS</h6>
        <p class="text-muted small mb-3">
            Unggah kode QRIS pribadimu -- akan otomatis ditampilkan ke penyewa begitu booking dikonfirmasi, supaya mereka tidak perlu tanya nomor rekening lagi lewat chat.
            KosKita tetap tidak memproses pembayaran apa pun; transfer tetap langsung ke kamu.
        </p>

        @if($owner->qris_image_path)
            <img src="{{ Storage::disk('public')->url($owner->qris_image_path) }}" alt="QRIS" style="max-width: 200px;" class="rounded-3 border mb-3 d-block">
            <form action="{{ route('web.owner.qris.delete') }}" method="POST" onsubmit="return confirm('Hapus kode QRIS ini?')" class="mb-3">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus QRIS</button>
            </form>
        @endif

        <form action="{{ route('web.owner.qris.upload') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            @csrf
            <input type="file" name="qris" accept="image/*" class="form-control" style="max-width: 320px;" required>
            <button type="submit" class="btn btn-primary-koskita">{{ $owner->qris_image_path ? 'Ganti QRIS' : 'Unggah QRIS' }}</button>
        </form>
    </div>

    <div class="mt-4">
        @include('web.partials.two-factor-settings')
    </div>

    <div class="mt-4">
        @include('web.partials.notification-preferences')
    </div>
</div>
@endsection
