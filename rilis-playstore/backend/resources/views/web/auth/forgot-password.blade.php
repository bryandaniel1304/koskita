@extends('web.layouts.app')

@section('title', 'Lupa Password')

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
                    <h4 class="fw-bold mt-3 mb-1">Lupa Password?</h4>
                    <p class="text-muted small mb-0">Masukkan email akunmu, kami kirimkan tautan buat atur ulang password.</p>
                </div>

                <form action="{{ route('web.password.email') }}" method="POST" class="js-loading-submit">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary-koskita w-100">Kirim Tautan Atur Ulang</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    Ingat password kamu? <a href="{{ route('web.login') }}" class="fw-bold" style="color: var(--primary);">Masuk di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
