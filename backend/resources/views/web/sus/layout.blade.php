<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') - KosKita</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #355DDB; --brand-soft: #EEF2FD; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F4F6FB; color: #1E293B; }
        .sus-card { background: #fff; border-radius: 20px; box-shadow: 0 8px 30px rgba(15, 23, 42, .06); }
        .sus-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; color: var(--brand); letter-spacing: .5px; }
        .sus-brand img { width: 36px; height: 36px; border-radius: 10px; }
        .btn-brand { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 700; border-radius: 12px; }
        .btn-brand:hover, .btn-brand:focus { background: #2A4BB8; border-color: #2A4BB8; color: #fff; }
        .form-control { border-radius: 12px; padding: .7rem .9rem; }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 .2rem rgba(53, 93, 219, .15); }
    </style>
</head>
<body>
    <main class="container py-4 py-md-5" style="max-width: 760px;">
        <div class="sus-brand mb-4">
            <img src="{{ asset('images/logo_icon.png') }}" alt="">
            KOSKITA
        </div>
        @yield('content')
    </main>
</body>
</html>
