# KosKita — Panduan Setup Layanan Eksternal

Dokumen ini isinya 6 hal yang **tidak bisa saya kerjakan sendiri** karena butuh akun/kredensial milikmu. Semuanya independen satu sama lain — kerjakan sesuai prioritas kebutuhanmu, tidak perlu berurutan.

Untuk tiap item: kalau kamu sudah punya kredensialnya, kirim ke saya (atau isi sendiri ke file yang disebutkan) dan saya sambungkan kodenya.

---

## 1. Crash Reporting — Sentry (gratis untuk skala kecil)

**Kenapa penting:** tanpa ini, kalau app crash di HP orang lain, kamu tidak akan pernah tahu — mereka cuma diam-diam uninstall.

**Langkah:**
1. Daftar gratis di [sentry.io](https://sentry.io) (free tier: 5.000 error/bulan, cukup untuk 150 responden).
2. Buat 2 project: satu "Flutter", satu "Laravel". Masing-masing dapat DSN (URL unik).
3. **Backend**: `composer require sentry/sentry-laravel`, lalu tambahkan `SENTRY_LARAVEL_DSN=<dsn-mu>` ke `backend/.env`.
4. **Frontend**: `flutter pub add sentry_flutter`, lalu bungkus `runApp()` di `main.dart` dengan `SentryFlutter.init()` memakai DSN Flutter-mu.

Kasih saya DSN-nya (2 buah), saya pasangkan kodenya sekarang.

---

## 2. Push Notification — Firebase Cloud Messaging (gratis)

**Kenapa penting:** status booking berubah, pesan baru, dll saat ini cuma kelihatan kalau user buka app manual.

**Langkah:**
1. Buat project di [console.firebase.google.com](https://console.firebase.google.com) (gratis, tidak perlu kartu kredit untuk FCM).
2. Tambahkan app Android (package name: `com.koskita.frontend`, sesuai `frontend/android/app/build.gradle.kts`) → download `google-services.json` → taruh di `frontend/android/app/`.
3. **Frontend**: `flutter pub add firebase_core firebase_messaging`, jalankan `flutterfire configure`.
4. **Backend**: butuh Service Account JSON dari Firebase Console (Project Settings → Service Accounts) untuk mengirim notifikasi dari Laravel. Package yang bisa dipakai: `kreait/laravel-firebase`.

Kasih saya `google-services.json` dan Service Account JSON-nya, saya sambungkan (termasuk trigger otomatis saat status booking berubah).

---

## 3. Login Sosial — Google Sign-In

**Kenapa penting:** mengurangi friksi registrasi (banyak user malas isi form manual).

**Langkah:**
1. Di [Google Cloud Console](https://console.cloud.google.com) → buat OAuth Client ID untuk Android (butuh SHA-1 fingerprint dari keystore-mu — jalankan `cd frontend/android && ./gradlew signingReport` untuk dapatkan itu) dan untuk Web (dipakai backend verifikasi token).
2. **Frontend**: `flutter pub add google_sign_in`.
3. **Backend**: endpoint baru `POST /api/auth/google` yang menerima ID token dari Flutter, verifikasi ke Google, lalu `firstOrCreate` User berdasarkan email.

Kasih saya Client ID (Web) yang didapat, saya buatkan endpoint backend + tombol "Masuk dengan Google" di Flutter.

---

## 4. Payment Gateway — Midtrans atau Xendit

**Kenapa penting:** supaya booking bisa disertai DP online (bukan cuma janji bayar manual ke pemilik) — standar platform marketplace properti.

**Langkah:**
1. Daftar merchant account di [midtrans.com](https://midtrans.com) atau [xendit.co](https://xendit.co) — perlu verifikasi bisnis (KTP, rekening bank), proses beberapa hari.
2. Mulai dari **Sandbox/Test Mode** dulu (langsung aktif tanpa verifikasi bisnis) untuk demo skripsi.
3. **Backend**: `composer require midtrans/midtrans-php` (atau Xendit PHP SDK), endpoint baru untuk generate Snap Token saat booking dikonfirmasi.
4. **Frontend**: buka halaman pembayaran Midtrans Snap lewat WebView atau redirect browser.

Ini yang paling berat setup-nya (verifikasi bisnis) — kalau cuma untuk demo skripsi, **Sandbox Mode saja sudah cukup** dan bisa langsung saya kerjakan begitu kamu kasih Server Key sandbox-nya (gratis, instan, tidak perlu verifikasi bisnis).

---

## 5. Rilis ke Play Store / App Store

**Kenapa penting:** supaya app bisa diunduh publik, bukan cuma APK manual.

| | Google Play Store | Apple App Store |
|---|---|---|
| Biaya | $25 sekali bayar | $99/tahun |
| Daftar di | [play.google.com/console](https://play.google.com/console) | [developer.apple.com](https://developer.apple.com) |
| Waktu review | ~1-3 hari | ~1-3 hari |

Setelah kamu punya akun developer, yang saya bisa siapkan:
- Ikon adaptif Android (sudah ada lewat `flutter_launcher_icons`, tinggal cek ukurannya sesuai spek terbaru).
- Draft deskripsi listing (judul, deskripsi singkat/panjang, kata kunci ASO) dalam Bahasa Indonesia & Inggris.
- Checklist screenshot yang dibutuhkan per ukuran layar.
- File `keystore` untuk signing APK release (perlu dibuat & disimpan aman — **jangan pernah hilang**, tanpa ini update app di masa depan tidak bisa dirilis ke akun yang sama).

---

## 6. HTTPS / Hosting Live

**Catatan:** ini yang sebelumnya kita hentikan sesuai arahanmu ("fokus ke pembuatan UI dan backend memakai XAMPP dulu"). Kalau nanti sudah siap deploy sungguhan (misalnya pas mulai kumpulkan data dari 150 responden yang butuh akses dari HP mereka masing-masing, bukan cuma dari jaringan WiFi-mu), opsi yang pernah kita bahas:
- **Railway.app / Render.com** — gratis untuk mulai, auto-HTTPS, cocok untuk Laravel + MySQL.
- **Hostinger** (yang pernah kamu tanyakan) — berbayar, tapi stabil 24/7, cocok kalau app sudah dipakai banyak orang.

Bilang saja kalau sudah mau mulai — saya bisa pandu step-by-step lagi (sempat kita bahas Render.com + Neon.tech sebelumnya).

---

## Ringkasan Prioritas (rekomendasi saya)

Untuk kebutuhan **demo skripsi ke 150 responden**, urutan yang paling masuk akal:
1. **Sentry** (gratis, 10 menit setup, langsung dapat visibility kalau ada bug pas testing)
2. **Hosting live** (kalau 150 responden tidak semua bisa akses WiFi-mu — ini jadi blocker teknis nyata)
3. Sisanya (push notif, payment gateway, social login, app store) — bagus untuk versi "produk sungguhan" pasca-skripsi, tapi tidak wajib untuk pembuktian ilmiah/demo.
