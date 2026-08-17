@extends('web.layouts.app')

@section('title', 'Syarat & Ketentuan')

@section('content')
<div class="container py-5">
    <div class="card-koskita p-4 p-md-5 mx-auto" style="max-width: 800px;">
        <h1 class="fw-bold mb-1" style="color: var(--primary);">Syarat &amp; Ketentuan KosKita</h1>
        <p class="text-muted">Terakhir diperbarui: {{ now()->format('d F Y') }}</p>

        <p>Dengan mendaftar dan menggunakan aplikasi KosKita, Anda setuju dengan syarat &amp; ketentuan berikut.</p>

        <h2 class="fw-bold h5 mt-4">1. Peran Pengguna</h2>
        <p>KosKita memfasilitasi dua jenis akun: <strong>Penyewa</strong> (mencari &amp; mengajukan booking kos) dan <strong>Penyedia Kos</strong> (mendaftarkan &amp; mengelola listing kos). Setiap akun hanya boleh memilih satu peran sesuai kebutuhan sebenarnya.</p>

        <h2 class="fw-bold h5 mt-4">2. Akurasi Data</h2>
        <p>Pengguna bertanggung jawab atas keakuratan data yang diinput (profil, data kos, deskripsi). Pemilik kos wajib mencantumkan informasi kos yang benar (harga, fasilitas, foto sesuai kondisi aktual).</p>

        <h2 class="fw-bold h5 mt-4">3. Booking &amp; Pembayaran</h2>
        <p>Fitur booking di KosKita adalah <strong>pengajuan</strong> yang dikonfirmasi langsung oleh pemilik kos. KosKita saat ini <strong>tidak</strong> memproses pembayaran/transaksi finansial apapun -- kesepakatan biaya sewa dilakukan langsung antara penyewa dan pemilik kos di luar aplikasi. KosKita tidak bertanggung jawab atas sengketa transaksi antara kedua pihak.</p>

        <h2 class="fw-bold h5 mt-4">4. Konten Pengguna (Review &amp; Ulasan)</h2>
        <p>Review yang ditulis pengguna bersifat publik dan menjadi tanggung jawab penulisnya. Dilarang menulis ulasan yang mengandung kebohongan, ujaran kebencian, atau konten yang melanggar hukum. KosKita berhak menghapus ulasan yang melanggar ketentuan ini.</p>

        <h2 class="fw-bold h5 mt-4">5. Verifikasi Akun</h2>
        <p>Beberapa aksi (mengajukan booking, mengelola kos, menulis review) mensyaratkan email terverifikasi sebagai langkah keamanan dasar.</p>

        <h2 class="fw-bold h5 mt-4">6. Rekomendasi Otomatis</h2>
        <p>Rekomendasi kos yang ditampilkan dihasilkan oleh algoritma otomatis (hybrid Content-Based &amp; Collaborative Filtering) berdasarkan profil dan riwayat interaksi pengguna. KosKita tidak menjamin akurasi mutlak rekomendasi tersebut.</p>

        <h2 class="fw-bold h5 mt-4">7. Konteks Penelitian</h2>
        <p>Aplikasi ini dikembangkan sebagai bagian dari penelitian tugas akhir (skripsi). Data yang dikumpulkan dapat dipakai untuk analisis akademik secara teragregasi/anonim, tanpa mengungkap identitas pengguna secara individual dalam laporan penelitian.</p>

        <h2 class="fw-bold h5 mt-4">8. Perubahan Ketentuan</h2>
        <p class="mb-0">Ketentuan ini dapat berubah seiring pengembangan aplikasi. Perubahan signifikan akan diinformasikan lewat aplikasi.</p>
    </div>
</div>
@endsection
