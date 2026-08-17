<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Kritis: dijalankan sebelum <body> dirender supaya tema gelap
        // (kalau tersimpan/disukai sistem) langsung aktif dari cat pertama
        // -- tanpa ini, halaman sempat "kedip" terang sesaat baru gelap.
        (function () {
            var saved = localStorage.getItem('koskita-theme');
            var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @php
        $pageTitle = trim((string) ($__env->yieldContent('title') ?: 'Cari Kos Terbaik'));
        $pageDescription = trim((string) ($__env->yieldContent('meta_description') ?: 'KosKita membantu kamu menemukan kos yang paling cocok di sekitar Karawaci, BSD, dan Serpong -- lengkap dengan rekomendasi otomatis, peta lokasi, dan ulasan penyewa lain.'));
        $pageOgImage = trim((string) ($__env->yieldContent('og_image') ?: asset('images/logo_icon.png')));
        $pageOgType = trim((string) ($__env->yieldContent('og_type') ?: 'website'));
    @endphp
    <title>{{ $pageTitle }} - KosKita</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Open Graph & Twitter Card -- supaya link yang di-paste ke WhatsApp/
         Instagram/Twitter langsung tampil kartu foto+judul+deskripsi, bukan
         teks polos. Ini murni markup, tidak butuh akun developer platform
         manapun untuk mulai berfungsi. --}}
    <meta property="og:site_name" content="KosKita">
    <meta property="og:type" content="{{ $pageOgType }}">
    <meta property="og:title" content="{{ $pageTitle }} - KosKita">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ $pageOgImage }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="id_ID">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }} - KosKita">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $pageOgImage }}">

    {{-- PWA -- "Tambah ke Layar Utama" tanpa lewat Play Store/App Store. --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#355DDB">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_icon.png') }}">

    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Kritis: dideklarasikan inline supaya overlay loading langsung
           tampil dari cat pertama (sebelum CSS eksternal selesai dimuat),
           mencegah "flash" halaman kosong sesaat sebelum overlay muncul. */
        #koskitaLoader {
            position: fixed; inset: 0; z-index: 10000;
            background: #F8FAFC;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        #koskitaLoader.koskita-loader-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
    </style>
    <style>
        :root {
            --primary: #355DDB;
            --primary-hover: #2137A2;
            --primary-light: #7091F9;
            --bg-light: #F8FAFC;
            --dark: #0F172A;
            /* Beda dari --dark (warna TEKS adaptif, jadi terang di mode
               gelap) -- ini permukaan aksen yang MEMANG selalu gelap
               (footer, pita CTA, blok kode), tidak pernah dibalik warnanya
               oleh mode tema. Dulu beberapa tempat salah pakai var(--dark)
               untuk latar gelap-permanen ini, jadi kelihatan terang saat
               mode gelap aktif -- teks putih di atasnya jadi tak kebaca. */
            --surface-dark: #0F172A;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--dark);
        }
        a { text-decoration: none; }
        .navbar-koskita {
            background-color: #FFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 14px 0;
        }
        .navbar-koskita .nav-link {
            color: #475569;
            font-weight: 600;
            font-size: 0.94rem;
            padding: 8px 14px !important;
            border-radius: 8px;
        }
        .navbar-koskita .nav-link.active,
        .navbar-koskita .nav-link:hover {
            color: var(--primary);
            background-color: rgba(53, 93, 219, 0.08);
        }
        .brand-koskita {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--dark) !important;
        }
        .brand-koskita img {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            object-fit: cover;
        }
        .btn-primary-koskita {
            background-color: var(--primary);
            border: none;
            font-weight: 700;
            padding: 10px 22px;
            border-radius: 10px;
            color: #FFF;
        }
        .btn-primary-koskita:hover { background-color: var(--primary-hover); color: #FFF; }
        .btn-outline-koskita {
            border: 1.5px solid #E2E8F0;
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 10px;
            color: var(--dark);
            background: #fff;
        }
        .btn-outline-koskita:hover { border-color: var(--primary); color: var(--primary); }
        .card-koskita {
            border: 1px solid #E2E8F0;
            border-radius: 18px;
            background: #FFF;
            box-shadow: 0 4px 14px -4px rgba(15, 23, 42, 0.06);
        }
        .badge-soft {
            background: rgba(53, 93, 219, 0.1);
            color: var(--primary);
            font-weight: 700;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.75rem;
        }
        /* Varian netral (abu-abu, bukan biru) badge-soft -- dipakai buat
           bedakan "Aturan" dari "Fasilitas" di halaman detail kos. Dulu
           warnanya di-hardcode inline (#F1F5F9/#475569) langsung di Blade,
           jadi tidak ikut gelap pas mode gelap aktif -- dipindah ke class
           di sini supaya bisa dikasih varian gelapnya juga. */
        .badge-neutral { background: #F1F5F9; color: #475569; }
        [data-bs-theme="dark"] .badge-neutral { background: rgba(255,255,255,0.06); color: #94A3B8; }
        .footer-koskita {
            background: var(--surface-dark);
            color: #94A3B8;
            padding: 48px 0 24px;
            margin-top: 80px;
        }
        .footer-koskita a { color: #CBD5E1; }
        .footer-koskita a:hover { color: #FFF; }
        .footer-koskita h6 { color: #FFF; font-weight: 700; }

        /* Loading screen bermerek -- tampil sesaat di kunjungan pertama &
           tiap kali berpindah halaman, supaya transisi terasa "hidup"
           alih-alih halaman putih kosong sekejap.

           CATATAN PERBAIKAN: sebelumnya cincin spinner di-posisikan absolut
           relatif ke KOTAK LUAR yang juga membungkus teks "KosKita" di
           bawahnya -- "top:50%" jadinya menghitung tengah dari logo+teks
           sekaligus, bukan cuma logo, jadi cincinnya nyangkut menimpa teks
           alih-alih melingkari logo dengan rapi. Sekarang logo+cincin
           dibungkus kotak sendiri (.koskita-loader-orbit) ukuran pas 84x84,
           teks di luar kotak itu -- cincin dijamin selalu pas melingkari
           logo berapa pun ukuran teksnya. */
        .koskita-loader-orbit {
            position: relative;
            width: 84px; height: 84px;
            display: flex; align-items: center; justify-content: center;
        }
        .koskita-loader-mark {
            width: 64px; height: 64px; border-radius: 18px; object-fit: cover;
            position: relative; z-index: 1;
            animation: koskita-pulse 1.4s ease-in-out infinite;
            box-shadow: 0 8px 24px -6px rgba(53, 93, 219, 0.45);
        }
        .koskita-loader-ring {
            position: absolute; inset: 0;
            border-radius: 50%;
            border: 3px solid rgba(53, 93, 219, 0.15);
            border-top-color: var(--primary);
            animation: koskita-spin 0.9s linear infinite;
        }
        .koskita-loader-text {
            margin-top: 18px; font-weight: 800; letter-spacing: 0.5px;
            color: var(--dark); font-size: 1.05rem;
        }
        @keyframes koskita-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        @keyframes koskita-spin {
            to { transform: rotate(360deg); }
        }

        /* Skeleton shimmer utk gambar kos sebelum selesai dimuat. */
        img.img-shimmer { background: linear-gradient(100deg, #ECEFF3 30%, #F6F8FA 50%, #ECEFF3 70%); background-size: 200% 100%; animation: koskita-shimmer 1.3s ease-in-out infinite; }
        @keyframes koskita-shimmer { to { background-position: -200% 0; } }

        /* Mode gelap -- Bootstrap 5.3 sudah otomatis menggelapkan komponen
           bawaannya sendiri (form, dropdown, alert, dst.) lewat atribut
           [data-bs-theme="dark"] di <html>; blok ini cuma menggelapkan
           komponen KUSTOM KosKita (navbar, kartu, footer, loader) yang
           warnanya di luar variabel Bootstrap. Palet sama persis dengan
           mode gelap app mobile (scaffoldBackgroundColor #0B1220,
           cardColor #1A2436) supaya identitas warnanya konsisten lintas
           platform. */
        [data-bs-theme="dark"] {
            --bg-light: #0B1220;
            --dark: #E2E8F0;
        }
        [data-bs-theme="dark"] body { background-color: var(--bg-light); color: var(--dark); }
        [data-bs-theme="dark"] .navbar-koskita { background-color: #1A2436; border-bottom-color: rgba(255,255,255,0.08); }
        [data-bs-theme="dark"] .navbar-koskita .nav-link { color: #94A3B8; }
        [data-bs-theme="dark"] .navbar-koskita .nav-link.active,
        [data-bs-theme="dark"] .navbar-koskita .nav-link:hover { color: var(--primary-light); background-color: rgba(112, 145, 249, 0.14); }
        [data-bs-theme="dark"] .brand-koskita { color: #E2E8F0 !important; }
        [data-bs-theme="dark"] .card-koskita { background: #1A2436; border-color: rgba(255,255,255,0.08); }
        [data-bs-theme="dark"] .btn-outline-koskita { background: #1A2436; border-color: rgba(255,255,255,0.14); color: #E2E8F0; }
        [data-bs-theme="dark"] .btn-outline-koskita:hover { border-color: var(--primary-light); color: var(--primary-light); }
        [data-bs-theme="dark"] .badge-soft { background: rgba(112, 145, 249, 0.16); }
        [data-bs-theme="dark"] .footer-koskita { background: #060A14; }
        [data-bs-theme="dark"] #koskitaLoader { background: #0B1220; }
        [data-bs-theme="dark"] .koskita-loader-text { color: #E2E8F0; }
        [data-bs-theme="dark"] img.img-shimmer { background: linear-gradient(100deg, #1A2436 30%, #223049 50%, #1A2436 70%); background-size: 200% 100%; }
        [data-bs-theme="dark"] ::selection { background: rgba(112, 145, 249, 0.35); }

        #themeToggle { width: 42px; height: 42px; padding: 0; flex-shrink: 0; }
    </style>
    @stack('styles')
    @stack('structured-data')
</head>
<body>
    <div id="koskitaLoader" aria-hidden="true">
        <div class="d-flex flex-column align-items-center">
            <div class="koskita-loader-orbit">
                <div class="koskita-loader-ring"></div>
                <img src="{{ asset('images/logo_icon.png') }}" alt="" class="koskita-loader-mark">
            </div>
            <span class="koskita-loader-text">KosKita</span>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-koskita sticky-top">
        <div class="container">
            <a class="navbar-brand brand-koskita" href="{{ route('web.home') }}">
                <img src="{{ asset('images/logo_icon.png') }}" alt="KosKita">
                KosKita
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navKoskita">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navKoskita">
                <ul class="navbar-nav mx-auto gap-1">
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('web.home') ? 'active' : '' }}" href="{{ route('web.home') }}">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('web.kos.*') ? 'active' : '' }}" href="{{ route('web.kos.index') }}">Cari Kos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('web.tips.*') ? 'active' : '' }}" href="{{ route('web.tips.index') }}">Tips Ngekos</a>
                    </li>
                    @auth
                        @php $unreadMessages = \App\Models\Message::where('receiver_id', Auth::id())->whereNull('read_at')->count(); @endphp
                        @if(Auth::user()->role === 'owner')
                            <li class="nav-item">
                                <a class="nav-link {{ Route::is('web.owner.*') ? 'active' : '' }}" href="{{ route('web.owner.dashboard') }}">Portal Pemilik</a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link {{ Route::is('web.recommendations') ? 'active' : '' }}" href="{{ route('web.recommendations') }}">Rekomendasi Untukmu</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ Route::is('web.bookings.*') ? 'active' : '' }}" href="{{ route('web.bookings.index') }}">Booking Saya</a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link position-relative {{ Route::is('web.messages.*') ? 'active' : '' }}" href="{{ route('web.messages.index') }}">
                                Pesan
                                @if($unreadMessages > 0)
                                    <span class="badge rounded-pill bg-danger" style="font-size: 0.6rem; vertical-align: top;">{{ $unreadMessages }}</span>
                                @endif
                            </a>
                        </li>
                    @endauth
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <button type="button" id="themeToggle" class="btn btn-outline-koskita d-flex align-items-center justify-content-center" title="Ganti mode terang/gelap" aria-label="Ganti mode terang/gelap">
                        <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
                    </button>
                    @auth
                        <div class="dropdown">
                            <button class="btn btn-outline-koskita dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                                @if(Auth::user()->avatar_url)
                                    <img src="{{ Auth::user()->avatar_url }}" alt="" class="rounded-circle" style="width: 20px; height: 20px; object-fit: cover;">
                                @else
                                    <i class="bi bi-person-circle"></i>
                                @endif
                                {{ Str::limit(Auth::user()->name, 16) }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px;">
                                <li><span class="dropdown-item-text text-muted small">{{ Auth::user()->email }}</span></li>
                                <li><hr class="dropdown-divider"></li>
                                @if(Auth::user()->role === 'owner')
                                    <li><a class="dropdown-item" href="{{ route('web.owner.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Portal Pemilik</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.owner.koses.index') }}"><i class="bi bi-houses me-2"></i>Kos Kamu</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.owner.bookings.index') }}"><i class="bi bi-calendar-check me-2"></i>Booking Masuk</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.owner.analytics') }}"><i class="bi bi-graph-up me-2"></i>Analitik</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.owner.settings') }}"><i class="bi bi-patch-check me-2"></i>Verifikasi &amp; QRIS</a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ route('web.bookings.index') }}"><i class="bi bi-calendar-check me-2"></i>Booking Saya</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.recommendations') }}"><i class="bi bi-stars me-2"></i>Rekomendasi Untukmu</a></li>
                                    <li><a class="dropdown-item" href="{{ route('web.profile') }}"><i class="bi bi-gear me-2"></i>Pengaturan Akun</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('web.messages.index') }}"><i class="bi bi-chat-square-text me-2"></i>Pesan @if($unreadMessages > 0)<span class="badge bg-danger rounded-pill">{{ $unreadMessages }}</span>@endif</a></li>
                                <li>
                                    <form action="{{ route('web.logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('web.login') }}" class="btn btn-outline-koskita">Masuk</a>
                        <a href="{{ route('web.register') }}" class="btn btn-primary-koskita">Daftar Gratis</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    @auth
        @unless(Auth::user()->hasVerifiedEmail())
            <div class="alert alert-warning border-0 rounded-0 mb-0 py-2">
                <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="bi bi-envelope-exclamation-fill me-2"></i>Email kamu belum diverifikasi. Beberapa aksi (booking, ulasan) akan terkunci sampai diverifikasi.</span>
                    <form action="{{ route('web.verification.resend') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning fw-bold">Kirim Ulang Email Verifikasi</button>
                    </form>
                </div>
            </div>
        @endunless
    @endauth

    <div class="container mt-4">
        @if (session('status'))
            <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show" style="border-radius: 12px;" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show" style="border-radius: 12px;" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                @if ($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    <main>
        @yield('content')
    </main>

    <footer class="footer-koskita">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4">
                    <div class="brand-koskita text-white mb-2">
                        <img src="{{ asset('images/logo_icon.png') }}" alt="KosKita">
                        KosKita
                    </div>
                    <p class="small mb-0">Platform rekomendasi kos untuk mahasiswa di Karawaci, BSD, dan Serpong -- mencocokkan kamu dengan kos yang paling sesuai budget, preferensi, dan gaya hidup lewat sistem rekomendasi hybrid.</p>
                </div>
                <div class="col-lg-2 col-6">
                    <h6>Jelajahi</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2">
                        <li><a href="{{ route('web.home') }}">Beranda</a></li>
                        <li><a href="{{ route('web.kos.index') }}">Cari Kos</a></li>
                        <li><a href="{{ route('web.tips.index') }}">Tips Ngekos</a></li>
                        <li><a href="{{ route('web.register') }}">Daftar Akun</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h6>Legal</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2">
                        <li><a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a></li>
                        <li><a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6>Punya Kos Sendiri?</h6>
                    <p class="small mb-0">Daftarkan kos kamu lewat aplikasi KosKita untuk Pemilik dan jangkau lebih banyak calon penyewa. Setelah punya akun, pantau booking & balas ulasan kapan saja lewat Portal Pemilik di situs ini juga.</p>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <p class="small mb-0 text-center">&copy; {{ date('Y') }} KosKita. Dibangun sebagai bagian dari penelitian skripsi sistem rekomendasi kos.</p>
        </div>
    </footer>

    @auth
        @include('web.partials.online-nanny')
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script src="{{ asset('js/web-loader.js') }}"></script>
    <script src="{{ asset('js/recently-viewed.js') }}"></script>
    <script src="{{ asset('js/theme-toggle.js') }}"></script>
    @stack('scripts')
    <script>
        // Daftarkan service worker -- lihat public/sw.js untuk kenapa
        // cakupannya sengaja minimal. Gagal diam-diam kalau browser tidak
        // dukung (mis. embed di iframe cross-origin) -- bukan fitur inti.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
