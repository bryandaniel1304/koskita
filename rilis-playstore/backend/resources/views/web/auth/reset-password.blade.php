@extends('web.layouts.app')

@section('title', 'Atur Ulang Password')

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
                    <h4 class="fw-bold mt-3 mb-1">Atur Ulang Password</h4>
                    <p class="text-muted small mb-0">Buat password baru buat akun KosKita kamu.</p>
                </div>

                <form action="{{ route('web.password.update') }}" method="POST" class="js-loading-submit">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Password Baru</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Ulangi Password Baru</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary-koskita w-100">Simpan Password Baru</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
