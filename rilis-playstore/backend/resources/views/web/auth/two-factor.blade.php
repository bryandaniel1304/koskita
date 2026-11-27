@extends('web.layouts.app')

@section('title', 'Verifikasi 2 Langkah')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center g-4 align-items-stretch">
        <div class="col-lg-5 d-none d-lg-block">
            @include('web.partials.auth-panel')
        </div>
        <div class="col-md-6 col-lg-5">
            <div class="card-koskita p-4 p-md-5 h-100">
                <div class="text-center mb-4">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:56px;height:56px;background:rgba(53,93,219,0.1);">
                        <i class="bi bi-shield-lock-fill fs-4" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Verifikasi 2 Langkah</h4>
                    <p class="text-muted small mb-0">Kami kirim kode 6 digit ke emailmu. Masukkan di bawah ini buat lanjut masuk.</p>
                </div>

                <form action="{{ route('web.2fa.verify') }}" method="POST" class="js-loading-submit">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode Verifikasi</label>
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                               class="form-control text-center @error('code') is-invalid @enderror"
                               style="font-size: 24px; letter-spacing: 8px; font-weight: 800;" required autofocus>
                        @error('code')
                            <div class="invalid-feedback text-center">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary-koskita w-100">Verifikasi</button>
                </form>

                <form action="{{ route('web.2fa.resend') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link w-100 small fw-bold text-decoration-none" style="color: var(--primary);">Kirim Ulang Kode</button>
                </form>

                <p class="text-center small text-muted mt-2 mb-0">
                    Bukan kamu? <a href="{{ route('web.login') }}" class="fw-bold" style="color: var(--primary);">Kembali ke halaman masuk</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
