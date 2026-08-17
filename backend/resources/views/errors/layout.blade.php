<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Terjadi Kesalahan') - KosKita</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_icon.png') }}">
    {{-- SENGAJA mandiri, tidak @extends('web.layouts.app') -- layout itu
         query database di navbar (jumlah pesan belum dibaca, dst.). Kalau
         error yang terjadi justru KEGAGALAN DATABASE, memuat layout itu
         buat nampilin halaman error-nya sendiri bisa gagal lagi (loop
         kegagalan kedua). Halaman error harus sesederhana & semandiri
         mungkin supaya tetap bisa tampil apa pun penyebab errornya. --}}
    <style>
        :root {
            --primary: #355DDB;
            --primary-hover: #2137A2;
            --bg-light: #F8FAFC;
            --dark: #0F172A;
            --muted: #64748B;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg-light: #0B1220; --dark: #E2E8F0; --muted: #94A3B8; }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background-color: var(--bg-light); color: var(--dark);
            font-family: -apple-system, "Segoe UI", "Plus Jakarta Sans", sans-serif;
            padding: 24px; text-align: center;
        }
        .wrap { max-width: 420px; }
        img.logo { width: 56px; height: 56px; border-radius: 14px; margin-bottom: 24px; }
        .code { font-size: 15px; font-weight: 800; letter-spacing: 0.08em; color: var(--primary); margin-bottom: 10px; }
        h1 { font-size: 22px; font-weight: 800; margin: 0 0 10px; }
        p.desc { color: var(--muted); font-size: 14.5px; line-height: 1.6; margin: 0 0 28px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; text-decoration: none; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-outline { border: 1.5px solid #E2E8F0; color: var(--dark); }
        @media (prefers-color-scheme: dark) { .btn-outline { border-color: rgba(255,255,255,0.14); } }
    </style>
</head>
<body>
    <div class="wrap">
        <img src="{{ asset('images/logo_icon.png') }}" alt="KosKita" class="logo">
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p class="desc">@yield('description')</p>
        <div class="actions">
            @yield('actions')
        </div>
    </div>
</body>
</html>
