@extends('web.layouts.app')

@section('title', 'Hapus Akun')

@section('content')
<div class="container py-5">
    <div class="card-koskita p-4 p-md-5 mx-auto" style="max-width: 800px;">
        <h1 class="fw-bold mb-1" style="color: var(--primary);">Hapus Akun KosKita</h1>
        <p class="text-muted">Halaman ini menjelaskan cara menghapus akun aplikasi KosKita beserta datanya.</p>

        <h2 class="fw-bold h5 mt-4">Cara 1 &mdash; Dari dalam aplikasi (langsung)</h2>
        <ol>
            <li>Buka aplikasi KosKita dan masuk ke akun Anda.</li>
            <li>Buka tab <strong>Profil</strong>, gulir ke bawah, lalu ketuk <strong>Hapus Akun</strong>.</li>
            <li>Ketik <strong>HAPUS</strong> untuk konfirmasi, lalu ketuk <strong>Hapus Permanen</strong>.</li>
        </ol>
        <p>Akun langsung terhapus saat itu juga.</p>

        <h2 class="fw-bold h5 mt-4">Cara 2 &mdash; Lewat permintaan</h2>
        <p>Jika Anda tidak bisa lagi masuk ke aplikasi, kirim permintaan penghapusan
            @if (config('koskita.email_kontak'))
                ke <a href="mailto:{{ config('koskita.email_kontak') }}?subject=Hapus%20Akun%20KosKita">{{ config('koskita.email_kontak') }}</a>
            @else
                kepada pengelola KosKita
            @endif
            dari alamat email yang terdaftar di akun Anda, dengan judul &ldquo;Hapus Akun KosKita&rdquo;. Permintaan diproses paling lambat 7 hari kerja.</p>

        <h2 class="fw-bold h5 mt-4">Data yang dihapus</h2>
        <ul>
            <li>Data akun: nama, email, nomor telepon, kata sandi, foto profil.</li>
            <li>Profil preferensi, riwayat dilihat/favorit/rating, dan rekomendasi.</li>
            <li>Riwayat booking, ulasan, dan pesan.</li>
            <li>Khusus akun Penyedia Kos: seluruh kos yang Anda daftarkan beserta fotonya, dokumen verifikasi, dan gambar QRIS.</li>
        </ul>

        <h2 class="fw-bold h5 mt-4">Data yang disimpan</h2>
        <p>Tidak ada data pribadi yang kami simpan setelah akun dihapus. Catatan log server teknis (tanpa isi data akun) terhapus otomatis paling lama 30 hari.</p>
    </div>
</div>
@endsection
