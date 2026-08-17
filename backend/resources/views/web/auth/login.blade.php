@extends('web.layouts.app')

@section('title', 'Masuk')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center g-4 align-items-stretch">
        <div class="col-lg-5 d-none d-lg-block">
            @include('web.partials.auth-panel')
        </div>
        <div class="col-md-6 col-lg-5">
            <div class="card-koskita p-4 p-md-5 h-100">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/logo_icon.png') }}" alt="KosKita" class="d-lg-none" style="width:48px;height:48px;border-radius:12px;">
                    <h4 class="fw-bold mt-3 mb-1">Masuk ke KosKita</h4>
                    <p class="text-muted small mb-0">Cari, simpan, dan ajukan booking kos favoritmu.</p>
                </div>

                <form action="{{ route('web.login.submit') }}" method="POST" class="js-loading-submit">
                    @csrf
                    <input type="hidden" name="redirect" value="{{ $redirect }}">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label small fw-bold text-muted mb-0">Password</label>
                            <a href="{{ route('web.password.request') }}" class="small fw-bold" style="color: var(--primary);">Lupa password?</a>
                        </div>
                        <input type="password" name="password" class="form-control mt-1" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Ingat saya</label>
                    </div>
                    <button type="submit" class="btn btn-primary-koskita w-100">Masuk</button>
                </form>

                @if(\App\Http\Controllers\Web\WebAuthController::googleLoginConfigured())
                    <div class="d-flex align-items-center gap-2 my-3">
                        <hr class="flex-grow-1"><span class="small text-muted">atau</span><hr class="flex-grow-1">
                    </div>
                    <a href="{{ route('web.google.redirect') }}" class="btn btn-outline-koskita w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-google"></i> Masuk dengan Google
                    </a>
                @endif

                <p class="text-center small text-muted mt-4 mb-0">
                    Belum punya akun? <a href="{{ route('web.register') }}" class="fw-bold" style="color: var(--primary);">Daftar gratis</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
