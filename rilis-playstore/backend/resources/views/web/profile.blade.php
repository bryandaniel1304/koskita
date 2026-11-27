@extends('web.layouts.app')

@section('title', 'Pengaturan Akun')

@section('content')
<div class="container py-4" style="max-width: 760px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="fw-bold mb-0">Pengaturan Akun</h4>
        <a href="{{ route('web.home') }}" class="btn btn-outline-koskita btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Beranda
        </a>
    </div>

    <div class="mb-4">
        @include('web.partials.avatar-settings')
    </div>

    <div class="card-koskita p-4 mb-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i> Info Akun</h6>
        <p class="small mb-1"><span class="text-muted">Nama:</span> {{ $user->name }}</p>
        <p class="small mb-0"><span class="text-muted">Email:</span> {{ $user->email }}</p>
    </div>

    @include('web.partials.two-factor-settings')

    <div class="mt-4">
        @include('web.partials.notification-preferences')
    </div>
</div>
@endsection
