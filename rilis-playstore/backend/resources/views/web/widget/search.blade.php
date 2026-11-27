<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Kos - Widget KosKita</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #355DDB; --primary-hover: #2137A2; --ink: #0F172A; --muted: #64748B; --line: #E2E8F0; }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin: 0; padding: 16px; background: #fff; color: var(--ink); }
        .brand { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1rem; margin-bottom: 12px; }
        .brand img { width: 24px; height: 24px; border-radius: 6px; }
        form { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        select, button {
            font-family: inherit; font-size: 0.85rem; padding: 9px 12px; border-radius: 9px;
            border: 1px solid var(--line);
        }
        select { flex: 1; min-width: 140px; }
        button {
            background: var(--primary); color: #fff; border: none; font-weight: 700; cursor: pointer; flex: none;
        }
        button:hover { background: var(--primary-hover); }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .card {
            border: 1px solid var(--line); border-radius: 10px; overflow: hidden; text-decoration: none; color: var(--ink);
            display: block;
        }
        .card img { width: 100%; height: 80px; object-fit: cover; display: block; }
        .card .body { padding: 8px 10px; }
        .card .name { font-weight: 700; font-size: 0.8rem; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card .price { color: var(--primary); font-weight: 800; font-size: 0.78rem; margin: 0; }
        .powered { margin-top: 12px; font-size: 0.7rem; color: var(--muted); text-align: center; }
        .powered a { color: var(--primary); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
    <div class="brand">
        <img src="{{ asset('images/logo_icon.png') }}" alt="">
        Cari Kos -- KosKita
    </div>

    {{-- target="_top" SENGAJA di semua tautan -- widget cuma titik masuk,
         hasil pencarian dibuka di tab penuh situs KosKita, bukan terjebak
         di dalam iframe kecil ini. --}}
    <form action="{{ route('web.kos.index') }}" method="GET" target="_top">
        <select name="location">
            <option value="">Semua lokasi</option>
            @foreach($locations as $loc)
                <option value="{{ $loc }}">{{ $loc }}</option>
            @endforeach
        </select>
        <button type="submit">Cari Kos</button>
    </form>

    @if($featured->isNotEmpty())
        <div class="cards">
            @foreach($featured as $kos)
                <a href="{{ route('web.kos.show', $kos->id) }}" target="_top" class="card">
                    <img src="{{ $kos->cover_image }}" alt="{{ $kos->name }}">
                    <div class="body">
                        <p class="name">{{ $kos->name }}</p>
                        <p class="price">Rp {{ number_format($kos->price / 1000000, 1) }}jt/bln</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <p class="powered">Didukung oleh <a href="{{ route('web.home') }}" target="_top">KosKita</a></p>
</body>
</html>
