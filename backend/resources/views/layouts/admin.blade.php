<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin KOSKITA - @yield('title')</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366F1;
            --primary-hover: #4F46E5;
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
            background-color: rgba(99, 102, 241, 0.15);
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
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div id="sidebar" class="d-flex flex-column justify-content-between">
        <div>
            <div class="px-4 py-4 d-flex align-items-center gap-2 border-bottom border-secondary mb-3">
                <i class="bi bi-house-gear-fill text-white fs-3"></i>
                <div>
                    <h5 class="text-white mb-0 fw-bold">KOSKITA</h5>
                    <small class="text-muted">Admin Panel</small>
                </div>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
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
                    <a class="nav-link {{ Route::is('admin.interactions') ? 'active' : '' }}" href="{{ route('admin.interactions') }}">
                        <i class="bi bi-activity"></i> Log Interaksi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.bookings.*') ? 'active' : '' }}" href="{{ route('admin.bookings.index') }}">
                        <i class="bi bi-calendar-check"></i> Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ Route::is('admin.facilities.*') || Route::is('admin.rules.*') ? 'active' : '' }}" href="{{ route('admin.facilities.index') }}">
                        <i class="bi bi-gear"></i> Fasilitas &amp; Aturan
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
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><i class="bi bi-person-circle"></i> {{ Auth::user()->name }}</span>
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
