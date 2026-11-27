@extends('web.layouts.app')

@section('title', 'Kebijakan Privasi')

@section('content')
<div class="container py-5">
    <div class="card-koskita p-4 p-md-5 mx-auto" style="max-width: 800px;">
        <h1 class="fw-bold mb-1" style="color: var(--primary);">Kebijakan Privasi KosKita</h1>
        <p class="text-muted">Terakhir diperbarui: {{ now()->format('d F Y') }}</p>

        <p>KosKita ("kami") menghargai privasi pengguna aplikasi ("Anda"). Dokumen ini menjelaskan data apa yang kami kumpulkan, bagaimana data itu dipakai, dan hak Anda atas data tersebut.</p>

        <h2 class="fw-bold h5 mt-4">1. Data yang Kami Kumpulkan</h2>
        <ul>
            <li><strong>Data akun:</strong> nama, alamat email, nomor telepon, kata sandi (disimpan terenkripsi).</li>
            <li><strong>Data profil preferensi:</strong> jenis kelamin, pekerjaan, rentang anggaran, lokasi yang diinginkan, fasilitas & aturan yang disukai.</li>
            <li><strong>Data interaksi:</strong> kos yang dilihat, difavoritkan, dirating, dan diajukan booking-nya -- dipakai untuk menghasilkan rekomendasi yang lebih relevan.</li>
            <li><strong>Data pemilik kos:</strong> untuk akun Penyedia Kos, kami juga menyimpan data kos yang didaftarkan (nama, harga, lokasi, foto).</li>
        </ul>

        <h2 class="fw-bold h5 mt-4">2. Bagaimana Data Digunakan</h2>
        <ul>
            <li>Menghasilkan rekomendasi kos yang dipersonalisasi (algoritma hybrid Content-Based & Collaborative Filtering).</li>
            <li>Memproses pengajuan booking antara penyewa dan pemilik kos.</li>
            <li>Verifikasi identitas (link verifikasi email) untuk mencegah penyalahgunaan akun.</li>
            <li>Analisis internal untuk pengembangan aplikasi (penelitian akademik, tidak dijual/dibagikan ke pihak ketiga untuk kepentingan komersial).</li>
        </ul>

        <h2 class="fw-bold h5 mt-4">3. Berbagi Data</h2>
        <p>Kami tidak menjual data pribadi Anda. Nama dan foto profil dasar dapat terlihat oleh pengguna lain dalam konteks review publik dan proses booking (pemilik kos dapat melihat nama & kontak penyewa yang mengajukan booking, dan sebaliknya).</p>

        <h2 class="fw-bold h5 mt-4">4. Keamanan Data</h2>
        <p>Kata sandi disimpan menggunakan hashing satu arah (bcrypt). Akses ke database dibatasi. Kami menggunakan token otentikasi (Sanctum) dengan masa berlaku terbatas untuk setiap sesi login.</p>

        <h2 class="fw-bold h5 mt-4">5. Hak Anda</h2>
        <ul>
            <li>Mengakses dan memperbarui data profil Anda kapan saja lewat halaman Profil di aplikasi.</li>
            <li>Meminta penghapusan akun & data terkait dengan menghubungi kami.</li>
            <li>Mereset riwayat interaksi/rating Anda lewat fitur "Reset Riwayat Rating & Favorit" di aplikasi.</li>
        </ul>

        <h2 class="fw-bold h5 mt-4">6. Kontak</h2>
        <p>Pertanyaan seputar privasi dapat diajukan lewat fitur Online Nanny di aplikasi atau menghubungi pengembang secara langsung.</p>

        <p class="text-muted small mt-4 mb-0">Dokumen ini dibuat untuk keperluan aplikasi penelitian skripsi KosKita dan akan diperbarui seiring pengembangan aplikasi.</p>
    </div>
</div>
@endsection
