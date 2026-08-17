@extends('web.layouts.app')

@section('title', $partner->name . ' -- Pesan')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <a href="{{ route('web.messages.index') }}" class="small text-muted d-inline-flex align-items-center gap-1 mb-3">
        <i class="bi bi-arrow-left"></i> Semua Pesan
    </a>

    <div class="card-koskita d-flex flex-column" style="height: 65vh;">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white" style="width:40px;height:40px;background:var(--primary);">
                {{ strtoupper(substr($partner->name, 0, 1)) }}
            </div>
            <div>
                <p class="fw-bold mb-0">{{ $partner->name }}</p>
                <p class="text-muted small mb-0 text-capitalize">{{ $partner->role === 'owner' ? 'Pemilik Kos' : 'Penyewa' }}</p>
            </div>
        </div>

        <div class="flex-grow-1 overflow-auto p-3" id="threadScroll">
            @forelse($messages as $m)
                @php $isMe = $m->sender_id === Auth::id(); @endphp
                <div class="d-flex mb-3 {{ $isMe ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="p-3" style="max-width: 75%; border-radius: 16px; {{ $isMe ? 'background: var(--primary); color: #fff; border-bottom-right-radius: 4px;' : 'background: var(--bg-light); border: 1px solid #E2E8F0; border-bottom-left-radius: 4px;' }}">
                        @if($m->kos)
                            <p class="mb-1" style="font-size: 0.72rem; font-style: italic; opacity: 0.85;">Tentang: {{ $m->kos->name }}</p>
                        @endif
                        @if($m->photo_url)
                            <a href="{{ $m->photo_url }}" target="_blank" rel="noopener">
                                <img src="{{ $m->photo_url }}" alt="Foto lampiran" class="rounded-3 mb-1 d-block" style="max-width: 220px; max-height: 220px; object-fit: cover;">
                            </a>
                        @endif
                        @if($m->body)
                            <p class="mb-1 small">{{ $m->body }}</p>
                        @endif
                        <p class="mb-0 text-end" style="font-size: 0.68rem; opacity: 0.75;">{{ $m->created_at->format('H:i') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-muted small text-center mt-4">Belum ada pesan. Mulai percakapan di bawah ini.</p>
            @endforelse
        </div>

        @error('message')
            <div class="alert alert-danger border-0 shadow-sm small mx-3 mt-3 mb-0 py-2">{{ $message }}</div>
        @enderror
        <form action="{{ route('web.messages.store') }}" method="POST" enctype="multipart/form-data" class="p-3 border-top">
            @csrf
            <input type="hidden" name="receiver_id" value="{{ $partner->id }}">
            @if($kosId)
                <input type="hidden" name="kos_id" value="{{ $kosId }}">
            @endif
            <div id="photoPreviewWrap" class="d-none mb-2">
                <img id="photoPreview" alt="Pratinjau foto" class="rounded-3" style="max-height: 80px;">
                <button type="button" id="photoPreviewRemove" class="btn btn-sm btn-outline-danger ms-2">Batal</button>
            </div>
            <div class="d-flex gap-2">
                <label class="btn btn-outline-koskita d-flex align-items-center justify-content-center mb-0" style="width: 44px; height: 44px; padding: 0; flex-shrink: 0;" title="Lampirkan foto">
                    <i class="bi bi-camera-fill"></i>
                    <input type="file" name="photo" id="photoInput" accept="image/*" class="d-none">
                </label>
                <input type="text" name="body" class="form-control" placeholder="Tulis pesan..." maxlength="2000" autofocus>
                <button type="submit" class="btn btn-primary-koskita d-flex align-items-center gap-1" style="width: 44px; height: 44px; padding: 0; flex-shrink: 0; justify-content: center;">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
{{-- Chat real-time lewat Laravel Reverb (self-hosted, tanpa akun pihak
     ketiga) -- pusher-js dipakai sebagai client karena Reverb bicara
     protokol yang sama persis dengan Pusher, cuma nunjuk ke server kita
     sendiri (bukan cloud Pusher). CATATAN: ini cuma benar-benar hidup
     kalau `php artisan reverb:start` sedang jalan di server -- kalau
     tidak, koneksi WebSocket gagal diam-diam (di-catch di bawah) dan
     chat tetap berfungsi normal lewat kirim pesan + refresh biasa,
     cuma pesan masuk tidak muncul otomatis tanpa reload. --}}
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-scroll ke pesan terbaru saat halaman dibuka.
        var el = document.getElementById('threadScroll');
        if (el) el.scrollTop = el.scrollHeight;

        // Pratinjau foto yang dipilih sebelum dikirim -- supaya pengguna
        // tahu foto mana yang bakal terlampir, bisa batal sebelum kirim.
        var input = document.getElementById('photoInput');
        var wrap = document.getElementById('photoPreviewWrap');
        var preview = document.getElementById('photoPreview');
        var removeBtn = document.getElementById('photoPreviewRemove');
        if (input) {
            input.addEventListener('change', function () {
                if (!input.files || !input.files[0]) return;
                preview.src = URL.createObjectURL(input.files[0]);
                wrap.classList.remove('d-none');
            });

            removeBtn.addEventListener('click', function () {
                input.value = '';
                wrap.classList.add('d-none');
            });
        }

        try {
            window.Pusher = Pusher;
            var echo = new Echo({
                broadcaster: 'reverb',
                key: @json(config('broadcasting.connections.reverb.key')),
                wsHost: @json(config('broadcasting.connections.reverb.options.host')),
                wsPort: @json((int) config('broadcasting.connections.reverb.options.port')),
                wssPort: @json((int) config('broadcasting.connections.reverb.options.port')),
                forceTLS: @json(config('broadcasting.connections.reverb.options.useTLS')),
                enabledTransports: ['ws', 'wss'],
            });

            echo.private('App.Models.User.{{ Auth::id() }}')
                .listen('.message.sent', function (e) {
                    // Kanal ini nampung SEMUA pesan masuk buat akun ini,
                    // dari siapa pun -- filter cuma yang dari lawan bicara
                    // thread yang lagi dibuka. Pesan dari orang lain tetap
                    // aman (badge notifikasi lain yang urus, cuma tidak
                    // ditambahkan ke thread yang salah di sini).
                    if (e.sender_id !== {{ (int) $partner->id }}) return;
                    appendIncomingMessage(e);
                });
        } catch (err) {
            // Server Reverb tidak jalan / gagal konek -- diam-diam saja,
            // chat tetap jalan normal lewat kirim+refresh biasa.
        }

        function appendIncomingMessage(e) {
            var container = document.getElementById('threadScroll');
            if (!container) return;
            var emptyState = container.querySelector('p.text-center');
            if (emptyState) emptyState.remove();

            var photoHtml = e.photo_url
                ? '<a href="' + e.photo_url + '" target="_blank" rel="noopener"><img src="' + e.photo_url + '" alt="Foto lampiran" class="rounded-3 mb-1 d-block" style="max-width:220px;max-height:220px;object-fit:cover;"></a>'
                : '';
            var bodyHtml = e.body ? '<p class="mb-1 small">' + escapeHtml(e.body) + '</p>' : '';
            var time = new Date(e.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

            var wrap = document.createElement('div');
            wrap.className = 'd-flex mb-3 justify-content-start';
            wrap.innerHTML = '<div class="p-3" style="max-width: 75%; border-radius: 16px; background: var(--bg-light); border: 1px solid #E2E8F0; border-bottom-left-radius: 4px;">' +
                photoHtml + bodyHtml +
                '<p class="mb-0 text-end" style="font-size: 0.68rem; opacity: 0.75;">' + time + '</p></div>';
            container.appendChild(wrap);
            container.scrollTop = container.scrollHeight;
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    });
</script>
@endpush
@endsection
