<?php

/*
|--------------------------------------------------------------------------
| Pengaturan KosKita -- versi RILIS (Play Store)
|--------------------------------------------------------------------------
*/

return [

    /*
     | Hanya kos yang sudah ditandai "Terverifikasi" oleh admin (kolom
     | koses.verified_at terisi) yang tampil di aplikasi & situs. Selalu
     | aktif di versi rilis; hanya bisa dimatikan sengaja lewat .env.
     */
    'hanya_kos_terverifikasi' => (bool) env('KOSKITA_HANYA_KOS_TERVERIFIKASI', true),

    /* Email kontak resmi yang tampil di Kebijakan Privasi, S&K, dan Hapus Akun. */
    'email_kontak' => env('KOSKITA_EMAIL_KONTAK'),

    /*
     | Akun master admin pertama, dibuat otomatis oleh deploy (perintah
     | `koskita:siapkan-master-admin`) HANYA jika belum ada satu pun admin.
     | Kata sandinya dibuat acak & dicetak sekali ke log deploy.
     */
    'master_admin_email' => env('KOSKITA_MASTER_ADMIN_EMAIL'),
    'master_admin_nama' => env('KOSKITA_MASTER_ADMIN_NAMA', 'Master Admin'),

];
