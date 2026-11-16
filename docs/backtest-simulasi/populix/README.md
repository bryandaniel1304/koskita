# Backtest Simulasi: 100 Pengguna Dummy Berbasis Responden Populix

> **Penting:** ini data DUMMY. Dari Populix hanya diambil identitas 100 responden
> (Id, gender, usia, kota). Populix hanya mengumpulkan jawaban SUS, jadi
> preferensi kos dan seluruh rating di sini dibangkitkan program, bukan jawaban
> responden. Hasilnya hanya boleh disebut **evaluasi berbasis simulasi** dan tidak
> boleh dilaporkan sebagai hasil 150 responden penelitian.

## Isi folder
- `backtest_populix.php`: pembangkit data dummy sekaligus evaluasi.
- `dummy_profil.csv`: 100 profil dummy (seed 42).
- `dummy_rating.csv`: 1.049 rating dummy (seed 42).
- `bt_<seed>.json`: hasil evaluasi per seed (1, 2, 3, 4, 5, 42).
- `ringkasan.txt`: rata-rata ± simpangan baku dari keenam seed.

## Cara data dibuat
- **Dari Populix:** gender (60 pria, 40 wanita) dan usia. Usia ≤ 23 dianggap mahasiswa (23 orang), selebihnya pekerja (77 orang).
- **Dibangkitkan:** area preferensi, rentang budget, 2–5 fasilitas, 0–2 aturan, dan 6–15 rating per pengguna. Generatornya sama dengan `../backtest.php` (lihat `../README.md`).
- **Katalog:** 239 kos asli beserta fasilitas dan aturannya.
- **Evaluasi:** `EvaluationService` milik research-dashboard, sama dengan yang dipakai skripsi. Kode `CollaborativeFilter` sudah versi 5 November 2026 (Skor_CF hanya dari rating tetangga).

## Hasil (rata-rata 6 seed)

**Warm-start** (±98 pengguna dievaluasi):

| Strategi | P@5 | R@5 | NDCG@5 | MAP@5 | P@10 | R@10 | NDCG@10 | MAP@10 |
|---|---|---|---|---|---|---|---|---|
| Hybrid (α=0,6) | 0,067 | 0,294 | 0,222 | 0,193 | 0,053 | 0,453 | 0,275 | 0,215 |
| CB murni | 0,065 | 0,297 | 0,223 | 0,195 | 0,048 | 0,416 | 0,264 | 0,211 |
| CF murni | 0,009 | 0,032 | 0,024 | 0,019 | 0,009 | 0,071 | 0,036 | 0,024 |
| Popularitas | 0,013 | 0,048 | 0,026 | 0,017 | 0,010 | 0,081 | 0,037 | 0,021 |

**Cold-start** (±99 pengguna dievaluasi):

| Strategi | P@5 | R@5 | NDCG@5 | MAP@5 | P@10 | R@10 | NDCG@10 | MAP@10 |
|---|---|---|---|---|---|---|---|---|
| Hybrid (switching α=1) | 0,236 | 0,252 | 0,273 | 0,201 | 0,209 | 0,416 | 0,340 | 0,233 |
| CB murni | 0,236 | 0,252 | 0,273 | 0,201 | 0,209 | 0,416 | 0,340 | 0,233 |
| CF murni | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Popularitas | 0,044 | 0,035 | 0,043 | 0,021 | 0,037 | 0,060 | 0,050 | 0,020 |

**Variasi α** (warm-start, NDCG@10): 0,267 (0,2) · 0,269 (0,3) · 0,274 (0,4) · 0,275 (0,5) · 0,275 (0,6) · 0,273 (0,7) · 0,273 (0,8).

## Cara membaca
1. **Cold-start:** hybrid sama dengan CB murni (α = 1) dan jauh di atas popularitas (NDCG@10 0,340 vs 0,050). CF murni nol karena pengguna baru tidak punya rating.
2. **Warm-start:** hybrid unggul di K = 10 (Recall 0,453 vs CB 0,416). Di K = 5 hybrid dan CB setara; selisihnya lebih kecil daripada simpangan baku antar-seed.
3. **α:** nilai 0,4–0,6 berada di dataran yang sama, jadi α = 0,6 masih wajar. Selisih antar-α juga di dalam simpangan baku.
4. Angka-angka ini mengikuti asumsi generator (cara rating dummy dibentuk), sehingga hanya menunjukkan bahwa sistem berjalan dan berperilaku sesuai rancangan, bukan seberapa baik rekomendasinya bagi pengguna nyata.

## Menjalankan ulang
```
php docs/backtest-simulasi/populix/backtest_populix.php <seed> <path_absolut_out.json> [folder_absolut_csv]
python3 docs/backtest-simulasi/bt_agg.py docs/backtest-simulasi/populix
```
Katalog dibaca dari MySQL lokal `koskita`. Bila MySQL tidak jalan, isi variabel lingkungan `CATALOG_SQLITE` dengan berkas SQLite berisi katalog. Path keluaran harus absolut karena skrip berpindah ke folder `research-dashboard`.
