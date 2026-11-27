@extends('web.layouts.app')

@section('title', 'Daftar')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center g-4 align-items-stretch">
        <div class="col-lg-5 d-none d-lg-block">
            @include('web.partials.auth-panel', [
                'headline' => 'Kenalan dulu, biar rekomendasinya tepat sasaran.',
                'subhead' => 'Isi preferensi sekali saat daftar -- budget, area, fasilitas -- dan langsung dapat rekomendasi kos yang relevan.',
                'points' => [
                    ['icon' => 'bi-person-check', 'text' => 'Daftar gratis, tanpa kartu kredit'],
                    ['icon' => 'bi-lightning-charge', 'text' => 'Rekomendasi langsung muncul setelah daftar'],
                    ['icon' => 'bi-shield-check', 'text' => 'Data preferensimu tidak dibagikan ke pihak ketiga'],
                ],
            ])
        </div>
        <div class="col-md-7 col-lg-6">
            <div class="card-koskita p-4 p-md-5 h-100">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo_icon.png') }}" alt="KosKita" class="d-lg-none" style="width:48px;height:48px;border-radius:12px;">
                    <h4 class="fw-bold mt-3 mb-1">Buat Akun KosKita</h4>
                    <p class="text-muted small mb-0">Gratis -- langsung dapat rekomendasi kos begitu daftar.</p>
                </div>

                <form action="{{ route('web.register.submit') }}" method="POST" class="js-loading-submit">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label small fw-bold text-muted">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label small fw-bold text-muted">No. HP</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="0812xxxxxxx" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Ulangi Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary-koskita w-100 mt-2">Daftar Sekarang</button>
                </form>

                @if(\App\Http\Controllers\Web\WebAuthController::googleLoginConfigured())
                    <div class="d-flex align-items-center gap-2 my-3">
                        <hr class="flex-grow-1"><span class="small text-muted">atau</span><hr class="flex-grow-1">
                    </div>
                    <a href="{{ route('web.google.redirect') }}" class="btn btn-outline-koskita w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-google"></i> Daftar dengan Google
                    </a>
                @endif

                <p class="text-center small text-muted mt-4 mb-0">
                    Sudah punya akun? <a href="{{ route('web.login') }}" class="fw-bold" style="color: var(--primary);">Masuk</a>
                </p>
                <p class="text-center small text-muted mt-2 mb-0">
                    Punya kos sendiri? Daftar sebagai pemilik lewat aplikasi KosKita untuk Pemilik.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
