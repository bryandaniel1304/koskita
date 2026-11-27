{{-- Kartu Pengaturan Notifikasi -- dipakai bareng di halaman pengaturan
     pemilik (web/owner/settings.blade.php) & penyewa (web/profile.blade.php),
     keduanya cuma butuh @include ini. Satu switch = mematikan email DAN
     push (FCM) sekaligus untuk jenis itu -- lihat method via() di
     NewMessageReceived/BookingStatusChanged/WaitlistSpotAvailable. --}}
<div class="card-koskita p-4">
    <h6 class="fw-bold mb-1"><i class="bi bi-bell me-1"></i> Pengaturan Notifikasi</h6>
    <p class="text-muted small mb-3">Atur jenis notifikasi (email & push) mana saja yang mau kamu terima.</p>

    <form action="{{ route('web.notifications.preferences') }}" method="POST">
        @csrf
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="notify_bookings" name="notify_bookings" value="1" {{ Auth::user()->notify_bookings ? 'checked' : '' }}>
            <label class="form-check-label small" for="notify_bookings">Status booking (dikonfirmasi/ditolak/selesai)</label>
        </div>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="notify_messages" name="notify_messages" value="1" {{ Auth::user()->notify_messages ? 'checked' : '' }}>
            <label class="form-check-label small" for="notify_messages">Pesan baru</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="notify_waitlist" name="notify_waitlist" value="1" {{ Auth::user()->notify_waitlist ? 'checked' : '' }}>
            <label class="form-check-label small" for="notify_waitlist">Kamar tersedia lagi (daftar tunggu)</label>
        </div>
        <button type="submit" class="btn btn-sm btn-primary-koskita">Simpan</button>
    </form>
</div>
