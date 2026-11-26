<?php

/*
|--------------------------------------------------------------------------
| Pengaturan khusus KosKita
|--------------------------------------------------------------------------
|
| Instance skripsi (koskita.kreasidigital-si.my.id) TIDAK mengisi variabel
| apa pun di bawah, jadi perilakunya persis seperti sebelum file ini ada.
| Nilai-nilai ini hanya dinyalakan di instance produksi untuk aplikasi
| Play Store (lihat rilis-playstore/PANDUAN-PLAYSTORE.md).
|
*/

return [

    /*
     | true = kos yang BELUM ditandai "Terverifikasi" oleh admin
     | (kolom koses.verified_at masih NULL) disembunyikan dari semua
     | tampilan publik: daftar, detail, peta, bandingkan, rekomendasi,
     | chatbot, dan situs web. Panel admin & panel pemilik tetap melihat
     | semuanya supaya kos baru bisa diperiksa lalu diverifikasi.
     */
    'hanya_kos_terverifikasi' => (bool) env('KOSKITA_HANYA_KOS_TERVERIFIKASI', false),

    /*
     | true = instance ini melayani aplikasi publik (Play Store), bukan
     | penelitian. Dipakai untuk menyesuaikan teks Kebijakan Privasi &
     | halaman Hapus Akun (tanpa menyebut skripsi/penelitian).
     */
    'rilis_publik' => (bool) env('KOSKITA_RILIS_PUBLIK', false),

    /* Email kontak resmi yang tampil di Kebijakan Privasi & Hapus Akun. */
    'email_kontak' => env('KOSKITA_EMAIL_KONTAK'),

];
