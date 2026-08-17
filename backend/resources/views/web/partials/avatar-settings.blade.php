{{-- Kartu Foto Profil -- dipakai bareng di halaman pengaturan pemilik
     (web/owner/settings.blade.php) & penyewa (web/profile.blade.php),
     keduanya cuma butuh @include ini. --}}
<div class="card-koskita p-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-person-circle me-1"></i> Foto Profil</h6>

    <div class="d-flex align-items-center gap-3 mb-3">
        @if(Auth::user()->avatar_url)
            <img src="{{ Auth::user()->avatar_url }}" alt="Foto profil" class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;">
        @else
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white" style="width: 64px; height: 64px; background: var(--primary); font-size: 1.5rem;">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
        @endif
        <div>
            <p class="fw-bold small mb-0">{{ Auth::user()->name }}</p>
            <p class="text-muted small mb-0">{{ Auth::user()->avatar_url ? 'Foto profil kamu saat ini' : 'Belum ada foto -- pakai lingkaran inisial nama' }}</p>
        </div>
    </div>

    <form action="{{ route('web.avatar.upload') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
        @csrf
        <input type="file" name="avatar" accept="image/*" class="form-control @error('avatar') is-invalid @enderror" style="max-width: 320px;" required>
        @error('avatar')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn-primary-koskita btn-sm">{{ Auth::user()->avatar_url ? 'Ganti Foto' : 'Unggah Foto' }}</button>
        @if(Auth::user()->avatar_url)
            <button type="submit" form="deleteAvatarForm" class="btn btn-sm btn-outline-danger">Hapus</button>
        @endif
    </form>
    @if(Auth::user()->avatar_url)
        <form id="deleteAvatarForm" action="{{ route('web.avatar.delete') }}" method="POST" class="d-none" onsubmit="return confirm('Hapus foto profil ini?')">
            @csrf @method('DELETE')
        </form>
    @endif
</div>
