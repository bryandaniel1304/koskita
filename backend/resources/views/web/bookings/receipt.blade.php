<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Booking #{{ $booking->id }} -- KosKita</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #355DDB; --ink: #0F172A; --muted: #64748B; --line: #E2E8F0; }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--ink); margin: 0; padding: 40px; background: #F8FAFC; }
        .sheet { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 16px; border: 1px solid var(--line); padding: 40px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.3rem; margin-bottom: 4px; }
        .brand img { width: 32px; height: 32px; border-radius: 8px; }
        .subtitle { color: var(--muted); font-size: 0.85rem; margin-bottom: 28px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #DCFCE7; color: #15803D; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        td { padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 0.92rem; }
        td:first-child { color: var(--muted); width: 45%; }
        td:last-child { font-weight: 700; text-align: right; }
        .total-row td { border-bottom: none; border-top: 2px solid var(--ink); padding-top: 16px; font-size: 1.1rem; }
        .note { margin-top: 28px; padding: 14px 16px; background: #F8FAFC; border-radius: 10px; font-size: 0.8rem; color: var(--muted); line-height: 1.5; }
        .print-btn { display: block; margin: 24px auto 0; padding: 10px 24px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
        @media print { .print-btn { display: none; } body { background: #fff; padding: 0; } .sheet { border: none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="brand">
            <img src="{{ asset('images/logo_icon.png') }}" alt="">
            KosKita
        </div>
        <p class="subtitle">Bukti Pengajuan Booking &mdash; #{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}</p>

        <span class="badge">{{ $booking->status === 'completed' ? 'Selesai' : 'Dikonfirmasi' }}</span>

        <table>
            <tr><td>Nama Kos</td><td>{{ $booking->kos->name ?? '-' }}</td></tr>
            <tr><td>Lokasi</td><td>{{ $booking->kos->location ?? '-' }}</td></tr>
            <tr><td>Pemilik</td><td>{{ $booking->kos->owner->name ?? '-' }}</td></tr>
            <tr><td>Penyewa</td><td>{{ $booking->user->name ?? Auth::user()->name }}</td></tr>
            <tr><td>Mulai Sewa</td><td>{{ $booking->start_date->translatedFormat('d F Y') }}</td></tr>
            <tr><td>Durasi</td><td>{{ $booking->duration_months }} bulan</td></tr>
            <tr><td>Status Pembayaran</td><td>{{ $booking->payment_status === 'paid' ? 'Sudah Dibayar' : 'Belum Dibayar' }}</td></tr>
            @if($booking->paid_at)
                <tr><td>Ditandai Lunas</td><td>{{ $booking->paid_at->translatedFormat('d F Y, H:i') }}</td></tr>
            @endif
            <tr class="total-row"><td>Sewa per Bulan</td><td>Rp {{ number_format($booking->kos->price ?? 0, 0, ',', '.') }}</td></tr>
        </table>

        <p class="note">
            Dokumen ini adalah bukti pengajuan booking yang telah dikonfirmasi pemilik kos, BUKAN kwitansi pembayaran resmi dari sistem finansial. KosKita tidak memproses atau menyimpan transaksi pembayaran apa pun -- transfer sewa dilakukan langsung antara penyewa dan pemilik kos.
        </p>

        <button class="print-btn" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>
</body>
</html>
