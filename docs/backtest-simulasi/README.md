# Backtest Simulasi 150 Pengguna Sintetis

> **Penting:** pengguna dan rating di sini SINTETIS (dibangkitkan program), bukan
> responden uji coba. Data uji coba asli di server per 30 Oktober 2026 baru berisi
> 3 rating dari 3 pengguna, sehingga belum bisa dipakai untuk backtest. Hasil di
> bawah hanya boleh dilaporkan sebagai **evaluasi berbasis simulasi**.

## Metode
- **Katalog:** 239 kos asli (sama dengan server), beserta fasilitas dan aturannya.
- **Pengguna:** 150 pengguna sintetis per percobaan, dengan profil berikut.
  - Gender acak.
  - Area diambil proporsional terhadap jumlah kos per area.
  - Rentang budget di sekitar harga satu kos di area itu.
  - 2–5 fasilitas dan 0–2 aturan yang diinginkan.
- **Selera tersembunyi:** tiap pengguna masuk salah satu dari 5 segmen yang tidak tercatat di profil.
  - Tiap segmen punya bobot harga, jarak, dan kelengkapan fasilitas.
  - Tiap segmen juga punya "kualitas tak teramati" per kos yang disukai bersama sesama anggotanya. Ini sinyal yang hanya bisa ditangkap CF.
- **Rating 1–5:** berasal dari utilitas = kecocokan fasilitas/aturan + skor segmen + kualitas tersembunyi + noise.
  - Utilitas dipotong jika kos melanggar gender, area, atau budget.
  - Tiap pengguna menilai 6–15 kos: 70% dari kos yang sesuai preferensi, sisanya jelajah bebas.
- **Evaluasi:** memakai `research-dashboard/app/Services/Evaluation/EvaluationService.php`, kode yang sama dengan dashboard skripsi.
  - Relevan = rating ≥ 4.
  - Warm-start: 20% rating relevan terbaru disembunyikan.
  - Cold-start: seluruh rating disembunyikan, sehingga hanya profil yang tersedia (α = 1).
  - Strategi yang dibandingkan: hybrid α = 0,6, CB murni, CF murni, popularitas.
- **Pengulangan:** 6 seed acak (1, 2, 3, 4, 5, 42). Angka di tabel = rata-rata ± simpangan baku.

## Hasil

**Warm-start** (±144 pengguna dievaluasi):

| Strategi | P@5 | R@5 | NDCG@5 | MAP@5 | P@10 | R@10 | NDCG@10 | MAP@10 |
|---|---|---|---|---|---|---|---|---|
| Hybrid (α=0,6) | 0,073±0,008 | 0,320±0,039 | 0,225±0,025 | 0,188±0,023 | 0,054±0,005 | 0,455±0,048 | 0,270±0,028 | 0,207±0,024 |
| CB murni | 0,065±0,006 | 0,284±0,022 | 0,208±0,022 | 0,178±0,023 | 0,050±0,004 | 0,424±0,029 | 0,255±0,025 | 0,197±0,024 |
| CF murni | 0,007±0,004 | 0,032±0,019 | 0,021±0,012 | 0,016±0,009 | 0,007±0,003 | 0,056±0,023 | 0,028±0,013 | 0,019±0,010 |
| Popularitas | 0,006±0,005 | 0,025±0,019 | 0,014±0,010 | 0,010±0,007 | 0,006±0,003 | 0,053±0,026 | 0,023±0,013 | 0,013±0,008 |

**Cold-start** (±149 pengguna dievaluasi):

| Strategi | P@5 | R@5 | NDCG@5 | MAP@5 | P@10 | R@10 | NDCG@10 | MAP@10 |
|---|---|---|---|---|---|---|---|---|
| Hybrid (switching α=1) | 0,233±0,015 | 0,258±0,022 | 0,278±0,021 | 0,208±0,019 | 0,207±0,010 | 0,417±0,019 | 0,342±0,019 | 0,237±0,021 |
| CB murni | 0,233±0,015 | 0,258±0,022 | 0,278±0,021 | 0,208±0,019 | 0,207±0,010 | 0,417±0,019 | 0,342±0,019 | 0,237±0,021 |
| CF murni | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Popularitas | 0,027±0,007 | 0,023±0,007 | 0,028±0,008 | 0,014±0,004 | 0,031±0,007 | 0,055±0,013 | 0,041±0,010 | 0,015±0,004 |

**Eksperimen α** (warm-start, NDCG@10):

| α | 0,2 | 0,3 | 0,4 | 0,5 | 0,6 | 0,7 | 0,8 |
|---|---|---|---|---|---|---|---|
| NDCG@10 | 0,264 | 0,263 | 0,266 | 0,269 | 0,270 | 0,271 | 0,267 |

## Interpretasi
1. **Warm-start:** hybrid unggul di semua metrik. Recall@10 hybrid 0,455 dibanding CB 0,424 (+7%), CF 0,056, dan popularitas 0,053.
   - CF murni lemah karena data jarang: kepadatan matriks hanya ±4%, sekitar 10 rating per pengguna dari 239 kos.
   - Namun CF tetap menambah sinyal ketika digabung dengan CB.
2. **Precision@K warm-start terlihat kecil** karena tiap pengguna hanya punya 1–2 kos uji. Batas atas P@5 sekitar 0,2–0,4, jadi Recall dan NDCG lebih representatif.
3. **Cold-start:** CF murni gagal total (semua 0). Ini membuktikan masalah cold-start.
   - Mekanisme switching (α = 1) membuat hybrid identik dengan CB.
   - Hasilnya sekitar 8× lebih baik daripada baseline popularitas (NDCG@10 0,342 vs 0,041).
4. **α optimal** berada di dataran 0,5–0,7. Selisihnya masih di dalam simpangan baku, jadi α = 0,6 yang dipakai skripsi berada di wilayah terbaik.
5. **Kesesuaian dengan preferensi** diuji terpisah pada 624 kombinasi profil, masing-masing dalam kondisi cold dan warm. Hasilnya: 0 rekomendasi melanggar gender, area, atau budget, dan 0 kos ganda.

## Menjalankan ulang
```
php docs/backtest-simulasi/backtest.php <seed> 150 <out.json>
python docs/backtest-simulasi/bt_agg.py <folder berisi bt_*.json>
```
Butuh MySQL lokal `koskita` (untuk katalog) dan dependensi `research-dashboard`. Data sintetis dibuat di SQLite sementara lalu dihapus. Database KosKita tidak diubah.
