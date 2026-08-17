{{-- Panel visual sisi form Masuk/Daftar -- sebelumnya halaman ini cuma
     kartu putih polos di layar kosong, paling sepi dibanding bagian situs
     lain yang sudah pakai gradient/kartu bergambar. Disembunyikan di layar
     sempit (form tetap jadi prioritas di mobile). --}}
<div class="d-none d-lg-flex flex-column justify-content-between h-100 p-5 text-white"
     style="background: linear-gradient(160deg, var(--primary) 0%, var(--primary-hover) 100%); border-radius: 20px; min-height: 560px;">
    <div>
        <div class="d-flex align-items-center gap-2 mb-5">
            <img src="{{ asset('images/logo_icon.png') }}" alt="" style="width:32px;height:32px;border-radius:9px;">
            <span class="fw-bold fs-5">KosKita</span>
        </div>
        <h2 class="fw-bold mb-3" style="font-size: 1.9rem; line-height: 1.25;">{{ $headline ?? 'Kos yang cocok, bukan sekadar kos yang kosong.' }}</h2>
        <p class="mb-0" style="color: rgba(255,255,255,0.82); max-width: 380px;">{{ $subhead ?? 'Rekomendasi dihitung dari preferensimu sendiri -- budget, lokasi, fasilitas -- bukan cuma daftar acak.' }}</p>
    </div>

    <div class="d-flex flex-column gap-3 mt-5">
        @foreach (($points ?? [
            ['icon' => 'bi-sliders', 'text' => 'Rekomendasi personal berbasis budget & preferensi'],
            ['icon' => 'bi-map', 'text' => 'Peta lokasi & jarak akurat ke kampus'],
            ['icon' => 'bi-chat-square-heart', 'text' => 'Ulasan asli dari penyewa sebelumnya'],
        ]) as $point)
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.16); flex-shrink: 0;">
                    <i class="bi {{ $point['icon'] }}"></i>
                </div>
                <span class="small" style="color: rgba(255,255,255,0.92);">{{ $point['text'] }}</span>
            </div>
        @endforeach
    </div>
</div>
