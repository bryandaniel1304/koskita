<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard Riset') - KOSKITA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #355DDB;
            --primary-light: #7091F9;
            --dark: #0F172A;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
            color: var(--dark);
        }
        .navbar-custom {
            background-color: var(--dark);
        }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link {
            color: #F8FAFC !important;
        }
        .navbar-custom .nav-link.active {
            color: #FFFFFF !important;
            font-weight: 700;
        }
        .badge-note {
            background-color: rgba(53, 93, 219, 0.1);
            color: var(--primary);
        }
        .card-custom {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stat-value { font-size: 28px; font-weight: 800; color: var(--dark); }
        .stat-label { font-size: 13px; color: #64748B; font-weight: 600; }
        .btn-primary-custom {
            background-color: var(--primary);
            border: none;
            color: #fff;
            font-weight: 700;
        }
        .btn-primary-custom:hover { background-color: #2137A2; color: #fff; }
        table th { font-size: 12.5px; text-transform: uppercase; color: #64748B; letter-spacing: 0.03em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">📊 KOSKITA Research Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Ringkasan</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('evaluation.*') ? 'active' : '' }}" href="{{ route('evaluation.index') }}">Evaluasi</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('respondents.*') ? 'active' : '' }}" href="{{ route('respondents.index') }}">Responden</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('sus.*') ? 'active' : '' }}" href="{{ route('sus.index') }}">SUS</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('sus.create') }}" target="_blank">Link Form SUS ↗</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 pb-5">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm" style="border-radius: 12px;">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>
</body>
</html>
