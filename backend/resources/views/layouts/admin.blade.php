<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin KOSKITA - @yield('title')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="{{ asset('js/loading-bar.js') }}"></script>
    <style>
        :root {
            --primary: #355DDB;
            --primary-hover: #2137A2;
            --primary-light: #7091F9;
            --bg-light: #F8FAFC;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
        }

        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            background-color: #0F172A;
            color: #94A3B8;
            transition: all 0.3s;
        }

        #sidebar .nav-link {
            color: #94A3B8;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 8px;
            margin: 4px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }

        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: #FFF;
            background-color: rgba(112, 145, 249, 0.2);
        }

        #sidebar .nav-link.active {
            background-color: var(--primary);
        }

        #content {
            margin-left: var(--sidebar-width);
            padding: 40px;
            min-height: 100vh;
        }

        .navbar-top {
            background-color: #FFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 16px 40px;
            margin: -40px -40px 40px -40px;
        }

        .card-custom {
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            background-color: #FFF;
            padding: 24px;
            margin-bottom: 24px;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        /* Kartu yang dibungkus <a> (mis. shortcut "Booking Menunggu") boleh
           terasa interaktif -- kartu statis lain tidak terpengaruh karena
           cuma jalan lewat parent hover, bukan class terpisah. */
        a:hover > .card-custom {
            box-shadow: 0 8px 20px -4px rgba(53, 93, 219, 0.12);
            transform: translateY(-2px);
            border-color: var(--primary-light);
        }

        .btn-primary-custom {
            background-color: var(--primary);
            border: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            color: #FFF;
        }

        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            color: #FFF;
        }

        .sidebar-section-label {
            padding: 4px 24px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748B;
        }

        .navbar-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #FFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .table-hover tbody tr {
            transition: background-color 0.15s;
        }

        /* Paginasi (hasil $paginator->links()) sengaja dikasih gaya sendiri
           di sini -- Bootstrap default-nya (kotak persegi nempel rapat,
           garis abu tipis) kontras banget sama komponen lain di dashboard
           ini yang semua sudah dibikin custom (kartu, tombol, avatar). Tiap
           tombol dipisah jadi pill bulat dengan jarak antar tombol, bukan
           nempel rapat kotak-kotak seperti bawaan Bootstrap. */
        .pagination {
            gap: 6px;
            flex-wrap: wrap;
        }

        .page-link {
            border: 1px solid #E2E8F0 !important;
            border-radius: 10px !important;
            margin: 0 !important;
            padding: 8px 14px;
            color: var(--primary);
            font-weight: 600;
            transition: all 0.15s;
        }

        .page-link:hover {
            background-color: rgba(53, 93, 219, 0.08);
            border-color: var(--primary-light) !important;
            color: var(--primary-hover);
        }

        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary) !important;
            color: #FFF;
            box-shadow: 0 4px 10px -2px rgba(53, 93, 219, 0.35);
        }

        .page-item.disabled .page-link {
            color: #CBD5E1;
            background-color: #F8FAFC;
            border-color: #E2E8F0 !important;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div id="sidebar" class="d-flex flex-column justify-content-between">
        <div>
            <div class="px-4 py-4 d-flex align-items-center gap-2 border-bottom border-secondary mb-3">
                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; overflow: hidden; flex-shrink: 0;">
                    <img src="{{ asset('images/logo_icon.png') }}" alt="KOSKITA" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div>
                    <h5 class="text-white mb-0 fw-bold">KOSKITA</h5>
                    <small style="color: #94A3B8;">Admin Panel</small>
                </div>
            </div>
            <p class="sidebar-section-label mb-1">Ringkasan</p>
            <ul class="nav flex-column mb-3">
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
            </ul>
            <p class="sidebar-section-label mb-1">Data</p>
            <ul class="nav flex-column mb-3">
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.koses.*') ? 'active' : '' }}" href="{{ route('admin.koses.index') }}">
                        <i class="bi bi-building-add"></i> Kelola Kos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.users') || Route::is('admin.users.show') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                        <i class="bi bi-people"></i> Data Responden
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}">
                        <i class="bi bi-calendar-check"></i> Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.interactions') ? 'active' : '' }}" href="{{ route('admin.interactions') }}">
                        <i class="bi bi-activity"></i> Log Interaksi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                        <i class="bi bi-flag"></i> Laporan
                        @if($pendingReportsCount ?? 0)
                            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingReportsCount }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.search-logs.*') ? 'active' : '' }}" href="{{ route('admin.search-logs.index') }}">
                        <i class="bi bi-search"></i> Pencarian Nihil
                    </a>
                </li>
            </ul>
            <p class="sidebar-section-label mb-1">Pengaturan</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.facilities.*') || Route::is('admin.rules.*') ? 'active' : '' }}" href="{{ route('admin.facilities.index') }}">
                        <i class="bi bi-gear"></i> Fasilitas &amp; Aturan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}">
                        <i class="bi bi-journal-text"></i> Tips Ngekos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.broadcasts.*') ? 'active' : '' }}" href="{{ route('admin.broadcasts.index') }}">
                        <i class="bi bi-megaphone"></i> Pengumuman
                    </a>
                </li>
            </ul>
        </div>
        <div class="p-3 border-top border-secondary">
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-box-arrow-right"></i> Keluar
                </button>
            </form>
        </div>
    </div>

    <!-- Content Area -->
    <div id="content">
        <!-- Top Navbar -->
        <div class="navbar-top d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold text-dark">@yield('page_name')</h4>
            <div class="d-flex align-items-center gap-2">
                <div class="navbar-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <span class="text-dark fw-semibold small">{{ Auth::user()->name }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>
