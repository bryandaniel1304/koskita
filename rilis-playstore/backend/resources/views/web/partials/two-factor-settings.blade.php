{{-- Kartu pengaturan Verifikasi 2 Langkah -- dipakai bareng di halaman
     pengaturan pemilik (web/owner/settings.blade.php) & penyewa
     (web/profile.blade.php), keduanya cuma butuh @include ini. --}}
<div class="card-koskita p-4">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
            <h6 class="fw-bold mb-1"><i class="bi bi-shield-lock me-1"></i> Verifikasi 2 Langkah</h6>
            <p class="text-muted small mb-0">Lapisan keamanan tambahan -- kode 6 digit dikirim ke emailmu tiap kali masuk, bukan cuma mengandalkan password.</p>
        </div>
        <span class="badge {{ Auth::user()->two_factor_enabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} flex-shrink-0">
            {{ Auth::user()->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}
        </span>
    </div>

    @if(Auth::user()->two_factor_enabled)
        <form action="{{ route('web.2fa.disable') }}" method="POST" onsubmit="return confirm('Nonaktifkan verifikasi 2 langkah? Login berikutnya cukup pakai password saja.')" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Nonaktifkan</button>
        </form>
    @elseif(session('confirmingTwoFactor'))
        <form action="{{ route('web.2fa.enable.confirm') }}" method="POST" class="d-flex flex-wrap gap-2 align-items-center mt-2">
            @csrf
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Kode dari email"
                   class="form-control @error('code') is-invalid @enderror" style="max-width: 200px; letter-spacing: 3px;" required autofocus>
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-primary-koskita btn-sm">Konfirmasi</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Kode konfirmasi sudah dikirim ke emailmu -- masukkan buat aktifkan.</p>
    @else
        <form action="{{ route('web.2fa.enable.start') }}" method="POST" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary-koskita">Aktifkan</button>
        </form>
    @endif
</div>
