# KosKita — Panduan Rilis ke Google Play Store

Folder `rilis-playstore/` adalah **sistem KosKita lengkap versi rilis**: aplikasi Android, backend (website publik, portal pemilik, panel admin & master admin, API), dan skrip deploy. Folder ini **berdiri sendiri** dan tidak memakai satu pun file skripsi (`frontend/`, `backend/`, `deploy/`), jadi skripsi tetap seperti semula:

| | Skripsi (tidak berubah) | Rilis Play Store (baru) |
|---|---|---|
| Aplikasi Flutter | `frontend/` | `rilis-playstore/app/` |
| Backend (website, admin, API) | `backend/` | `rilis-playstore/backend/` |
| ID aplikasi Android | `com.koskita.frontend` | `com.koskita.app` |
| Server/backend | `~/koskita` → koskita.kreasidigital-si.my.id | `~/koskita-playstore` → subdomain baru |
| Database | database skripsi (data scraping, responden, SUS) | **database baru**, kosong |
| Kos yang tampil | semua | **hanya kos yang diverifikasi admin** |
| Branch git yang di-deploy | `main` | `rilis-playstore` |

**Isi backend rilis dibanding skripsi:**
- **Sistem rekomendasi identik byte-per-byte** dengan yang diuji di skripsi: `RecommendationService`, Content-Based, Collaborative Filtering, Cosine Similarity, dan Switching-Weighted Combiner. Tes rekomendasinya juga sama dan lulus.
- Website publik, portal pemilik, panel admin, dan master admin semuanya ikut. Istilah riset ("Responden", "skripsi", "penelitian") diganti istilah produk.
- **Dibuang karena khusus riset:** kuesioner & menu SUS, scraper Mamikos/Infokost/Rukita, impor/ekspor data riset, dan seeder data contoh (`db:seed` hanya mengisi master fasilitas & aturan).
- **Ditambah:** filter "hanya kos terverifikasi" (selalu aktif), hapus akun mandiri + halaman `/hapus-akun`, dan pembuatan master admin pertama otomatis saat deploy.
- 271 tes backend rilis lulus. Alur end-to-end juga sudah dicoba di database kosong: pemilik daftar & tambah kos → kos tersembunyi → admin verifikasi di panel → kos tampil di website, API, dan rekomendasi → hapus akun.

---

## ⚠️ Baca dulu: realistis soal tenggat "sebelum Desember"

Aturan Google Play untuk **akun developer pribadi (personal) yang dibuat sejak November 2023**:
aplikasi wajib menjalani **closed testing dengan minimal 12 tester yang ikut terus-menerus selama 14 hari**, baru boleh mengajukan akses produksi (ditinjau lagi ± 7 hari).

Artinya, kalau klien memakai akun personal baru:
- **Bisa sebelum Desember:** aplikasi sudah ada di Play Store sebagai *closed testing* (diunduh lewat link undangan dari Play Store).
- **Tersedia untuk umum (production):** paling cepat ± pertengahan–akhir Desember.

Jalur yang tidak kena syarat 14 hari:
1. **Akun organisasi** (atas nama usaha/PT/CV klien). Syaratnya **nomor D-U-N-S** (gratis, tetapi pengurusannya bisa beberapa hari sampai beberapa minggu) dan verifikasi organisasi.
2. **Akun developer lama milik klien** (dibuat sebelum November 2023). Akun ini bisa langsung mengajukan production.

Sampaikan hal ini ke klien hari ini juga. Paling aman, **mulai closed testing secepatnya** supaya hitungan 14 hari langsung berjalan.

---

## Langkah 1 — Akun Google Play Console (klien)

Akun sebaiknya **milik klien**, karena aplikasi akan tercatat atas namanya.

1. Buka <https://play.google.com/console/signup> dengan akun Google klien.
2. Pilih **Pribadi** atau **Organisasi** (lihat penjelasan di atas).
3. Bayar biaya pendaftaran sekali seumur hidup (USD 25).
4. Selesaikan **verifikasi identitas** (KTP/dokumen usaha) dan **verifikasi nomor HP**. Proses ini bisa memakan 1–3 hari, jadi kerjakan paling awal.
5. Minta klien menambahkan email kamu sebagai pengguna: **Users and permissions → Invite new users → Admin**. Dengan begitu kamu bisa mengunggah build tanpa memegang kata sandi klien.

## Langkah 2 — Server produksi (cPanel, terpisah dari skripsi)

### 2a. Subdomain + SSL
1. cPanel → **Domains → Create A New Domain**: buat subdomain produksi, misalnya `app.<domain-klien>`. Atur *Document Root* ke `public_html/koskita-app`.
2. cPanel → **SSL/TLS Status → Run AutoSSL**. Aplikasi rilis **hanya menerima HTTPS**.

### 2b. Database baru
1. cPanel → **MySQL Databases**: buat database baru dan user baru, lalu beri user itu *ALL PRIVILEGES* ke database tersebut.
2. **Jangan memakai database skripsi.**

### 2c. Branch `rilis-playstore`
Branch ini **sudah dibuat dan ada di GitHub**. Isinya sama dengan branch skripsi, kecuali `.cpanel.yml`-nya menjalankan `rilis-playstore/server/deploy.sh`. Branch skripsi tetap memakai `.cpanel.yml` aslinya.

### 2d. Clone kedua di cPanel
1. cPanel → **Git™ Version Control → Create**.
2. Isi *Clone URL* dengan repo yang sama seperti `~/koskita`. Isi *Repository Path* dengan `koskita-playstore`.
3. Buka **Manage** pada repo itu, ubah *Checked-Out Branch* menjadi `rilis-playstore`, lalu klik **Update from Remote**.

### 2e. `.env` produksi
1. File Manager: salin `~/koskita/backend/.env` (milik skripsi) ke `~/koskita-playstore/rilis-playstore/backend/.env`.
2. Ubah isinya sesuai `rilis-playstore/server/env-produksi.contoh`: `APP_URL`, `DB_*`, `APP_KEY` dikosongkan, `QUEUE_CONNECTION`, dan baris `KOSKITA_*` (email kontak & email master admin).
3. Kalau `.env` skripsi memakai Pusher, buat app Pusher baru untuk produksi (penjelasannya ada di file contoh).
4. Di cPanel Git → **Deploy HEAD Commit**. Hasilnya bisa dicek di `~/koskita-playstore-deploy.log`; baris terakhir harus `== selesai`.
   Script deploy **menolak berjalan** kalau `.env` mencoba mematikan filter kos terverifikasi, sebagai pengaman.
5. Buka `https://app.<domain-klien>/privacy` dan `/hapus-akun` di browser untuk memastikan servernya hidup.

### 2f. Akun master admin
1. Deploy pertama otomatis membuat master admin dari `KOSKITA_MASTER_ADMIN_EMAIL`. Kata sandi acaknya tercetak **sekali** di `~/koskita-playstore-deploy.log` (baris "Password digenerate otomatis").
2. Login di `https://app.<domain-klien>/login`, lalu segera ganti kata sandinya.
3. Admin operasional tambahan: daftarkan akun biasa, lalu master admin mengubah role-nya di **Data Pengguna → Ubah**.

### 2g. Mengisi kos klien (hanya yang sudah bekerja sama)
1. Klien (atau kamu atas nama klien) daftar di aplikasi sebagai **Penyedia Kos**, verifikasi email, lalu tambahkan kos lengkap dengan foto.
2. Admin → menu **Kos** → tandai kos itu **Terverifikasi**. Kos baru muncul di aplikasi setelah langkah ini.
3. Kos yang belum diverifikasi tetap terlihat di panel admin dan panel pemilik, tetapi **tidak pernah tampil** ke pengguna aplikasi atau situs.
4. Admin juga bisa menambah kos langsung lewat **Kelola Kos → Tambah Kos Baru**, lalu menandainya Terverifikasi. Kos seperti ini tidak punya akun pemilik, jadi pengajuan booking-nya dikelola admin lewat menu **Booking**. Kalau klien ingin menerima booking & chat sendiri, pakai cara nomor 1.
5. Versi rilis tidak punya fitur scraping/impor data riset, dan `db:seed` hanya mengisi master fasilitas & aturan, jadi data skripsi tidak mungkin masuk.
6. Area kos yang bisa dipilih masih **Karawaci, BSD, Serpong, dan Alam Sutera** (sama dengan skripsi). Kalau kos klien di luar area ini, kabari saya dulu sebelum rilis supaya daftar areanya ditambah.

### 2h. Cron (email & notifikasi)
Email verifikasi, reset kata sandi, dan notifikasi booking/pesan dikirim lewat antrean. cPanel → **Cron Jobs → Add New Cron Job**, pilih *Once Per Minute*, lalu isi command:

```bash
cd $HOME/koskita-playstore/rilis-playstore/backend && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 > /dev/null 2>&1
```

Tanpa cron ini, pengguna tidak akan menerima email verifikasi sehingga tidak bisa booking atau chat.

### 2i. Cadangan database
Aktifkan **cPanel → Backup** (atau JetBackup kalau tersedia) untuk database produksi. Perintah backup bawaan KosKita khusus Windows/XAMPP dan tidak dipakai di cPanel.


## Langkah 3 — Kunci tanda tangan (upload key)

Kerjakan **sekali saja**, lalu **simpan cadangan file `.jks` dan kata sandinya** (misalnya di Google Drive klien dan password manager). Kalau hilang, kamu harus meminta reset upload key ke Google.

Dari folder `rilis-playstore/app/android`:

```bash
keytool -genkey -v -keystore koskita-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

Setelah itu salin `key.properties.contoh` menjadi `key.properties`, lalu isi kedua kata sandinya. File `.jks` dan `key.properties` sudah di-`.gitignore`, jadi tidak akan ter-commit.

## Langkah 4 — Build file `.aab`

Mac ini belum punya Android SDK. Ada dua pilihan: pasang **Android Studio** (beserta SDK-nya), atau build di laptop Windows yang dulu dipakai untuk build APK skripsi.

1. Isi alamat server produksi di `rilis-playstore/app/lib/config/app_config.dart` (`_produksiBaseUrl`). Ini cadangan; build script tetap mengirim alamatnya.
2. Jalankan build:
   - Mac/Linux:
     ```bash
     API_BASE_URL=https://app.domain-klien/api bash rilis-playstore/build-aab.sh
     ```
   - Windows (dari folder `rilis-playstore`): `build-aab.bat https://app.domain-klien/api`
3. Hasilnya ada di `rilis-playstore/hasil-build/`.
4. **Uji dulu di HP sungguhan**: buka `rilis-playstore/app` lalu jalankan `flutter run --release --dart-define=API_BASE_URL=https://app.domain-klien/api`. Pastikan daftar, login, kos tampil, booking, dan Profil → Hapus Akun semuanya berjalan.
5. Setiap kali mengunggah build baru, naikkan `version:` di `rilis-playstore/app/pubspec.yaml`, misalnya `1.0.0+1` → `1.0.1+2`. Angka setelah `+` wajib selalu naik.

## Langkah 5 — Buat aplikasi di Play Console

**Create app**: nama `KosKita`, bahasa default Indonesia, jenis *App*, *Free*, lalu centang semua pernyataan.

Di **Dashboard → Set up your app**, isi semua bagian berikut.

| Bagian | Isi |
|---|---|
| **Privacy policy** | `https://app.<domain-klien>/privacy` |
| **App access** | *All or some functionality is restricted*. Berikan **akun demo penyewa dan akun demo pemilik** (email sudah terverifikasi) supaya reviewer Google bisa login. Tanpa ini, aplikasi biasanya ditolak. |
| **Ads** | No, my app does not contain ads |
| **Content rating** | Isi kuesioner IARC: kategori *Utility/Productivity* atau *Lifestyle*. Jawab "ya" untuk *users can interact/communicate* (fitur chat), sisanya "tidak". |
| **Target audience** | 18+ (hindari memilih usia di bawah 13 karena memicu kebijakan Families) |
| **News app** | No |
| **Data safety** | Lihat tabel di bawah |
| **Government app / Financial features / Health** | No / Tidak ada / Tidak ada |
| **Delete account URL** (di Data safety) | `https://app.<domain-klien>/hapus-akun` |

**Data safety**: data dikirim terenkripsi (HTTPS) dan pengguna bisa meminta penghapusan (ya, tersedia in-app dan lewat URL).

| Jenis data | Dikumpulkan | Tujuan |
|---|---|---|
| Nama, email, nomor telepon | Ya, wajib | Account management, App functionality |
| Foto (foto profil, foto kos, foto ulasan/chat) | Ya, opsional | App functionality |
| Pesan dalam aplikasi (chat penyewa ↔ pemilik) | Ya | App functionality |
| Interaksi aplikasi (kos dilihat/favorit/rating) | Ya | App functionality, Personalization (rekomendasi) |
| Device ID (token notifikasi push) | Ya, kalau Firebase dipasang | App functionality |
| Crash logs | Ya, kalau Sentry dipasang | Analytics |
| Lokasi | **Tidak.** Lokasi kos dipilih manual di peta, bukan GPS HP | — |

Data tidak dibagikan ke pihak ketiga dan tidak dijual.

**Main store listing**:
- Nama: KosKita (maksimal 30 karakter).
- Deskripsi singkat (maksimal 80 karakter), contoh: *Cari kos terverifikasi, bandingkan, dan ajukan booking langsung ke pemilik.*
- Deskripsi lengkap: jelaskan fitur rekomendasi, kos terverifikasi, chat, dan booking.
- Ikon 512×512 PNG: pakai `rilis-playstore/app/assets/icon/icon.png` yang di-resize ke 512×512.
- Feature graphic 1024×500 PNG (bisa dibuat di Canva).
- Minimal **2 screenshot HP**, sebaiknya 4–8, diambil dari HP yang menjalankan build produksi berisi kos klien.

## Langkah 6 — Unggah & rilis

1. **Testing → Closed testing → Create track**. Buat daftar tester berisi minimal 12 email Gmail (teman, keluarga, staf klien).
2. **Create new release**: terima **Play App Signing** (Google yang menyimpan kunci aplikasinya), lalu unggah `.aab` dari `hasil-build/`.
3. Isi catatan rilis, lalu **Review release → Start rollout**. Review pertama biasanya memakan beberapa jam sampai beberapa hari.
4. Bagikan **link opt-in** ke para tester. Mereka harus menekan "Become a tester", **memasang aplikasinya, dan tidak keluar dari program selama 14 hari**.
5. Setelah 14 hari (akun personal), buka **Dashboard → Apply for production**, isi kuesioner tentang hasil pengujian, lalu tunggu persetujuan (± 7 hari).
6. **Production → Create new release** → unggah `.aab` dengan versi lebih tinggi, atau *promote* rilis dari closed testing.

Akun organisasi, atau akun yang dibuat sebelum November 2023, bisa langsung ke langkah 6 tanpa menunggu 14 hari.

## Langkah 7 — Opsional (bisa menyusul setelah rilis)

- **Login Google**: buat OAuth Client Android di Google Cloud dengan package `com.koskita.app` dan SHA-1 **App signing key**. SHA-1 ini ada di Play Console → *Setup → App integrity*, **bukan** SHA-1 upload key. Lalu isi Client ID Web di `.env` produksi. Selama belum diisi, tombol "Masuk dengan Google" tidak berfungsi, sedangkan login email tetap normal.
- **Push notification (Firebase)**: tambahkan app Android `com.koskita.app` di Firebase, lalu taruh `google-services.json` di `rilis-playstore/app/android/app/`. Tanpa file itu, push nonaktif secara diam-diam dan pengingat lokal tetap jalan.
- **Sentry**: tambahkan `--dart-define=SENTRY_DSN=...` di build script.

## Memperbarui aplikasi/server di kemudian hari

- Semua perubahan versi rilis dikerjakan **di dalam `rilis-playstore/`** (`app/` untuk aplikasi, `backend/` untuk website/admin/API). Perubahan di `frontend/` atau `backend/` skripsi **tidak** ikut ke rilis, dan sebaliknya.
- Setelah commit, bawa perubahan ke branch rilis lalu deploy:
  ```bash
  git checkout rilis-playstore && git merge feat/tampilkan-data-riset-lengkap && git push && git checkout feat/tampilkan-data-riset-lengkap
  ```
  Lalu di cPanel, pada repo `koskita-playstore`: **Update from Remote → Deploy HEAD Commit**.

## Perbedaan isi aplikasi rilis dibanding aplikasi skripsi

- Alamat server dikunci ke produksi: tombol "Pengaturan Server" tidak ada, dan HTTP biasa (non-HTTPS) diblokir.
- Ditambah menu **Profil → Hapus Akun** (penyewa & pemilik), yang wajib menurut kebijakan Google Play.
- Ditandatangani upload key rilis (bukan debug key) dan menargetkan Android API 36.
- Hanya Android. Folder iOS/web/desktop tidak ikut disalin.
- Nama aplikasi di HP: "KosKita". Karena ID-nya berbeda, aplikasi ini bisa terpasang berdampingan dengan versi skripsi.
