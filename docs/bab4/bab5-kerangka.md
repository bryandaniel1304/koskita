# BAB V KESIMPULAN DAN SARAN

> **CATATAN PENTING:** Bab ini sengaja HANYA berisi kerangka/struktur, bukan isi final.
> Kesimpulan yang sah secara akademik harus disusun berdasarkan HASIL EVALUASI NYATA
> (Precision@K, Recall@K, NDCG, MAP dari `EvaluationService`, dan skor SUS dari
> kuesioner) setelah 150 responden benar-benar menggunakan prototipe. Karena data itu
> belum ada, mengisi bagian ini dengan angka/klaim sekarang berarti mengarang data --
> jangan dilakukan. Isi placeholder `[ISI SETELAH EVALUASI]` di bawah setelah Anda
> menjalankan `research-dashboard` (`compareBaselines`, `compareAlphas`) dan
> mengumpulkan respons SUS sungguhan.

## 5.1 Kesimpulan

Kesimpulan disusun menjawab keempat Perumusan Masalah (Subbab 1.2) satu per satu. Pola kalimat di bawah bisa diikuti, tinggal lengkapi angka aktual dari hasil evaluasi.

1. **Menjawab Perumusan Masalah poin 1** (rancangan model hybrid): Penelitian ini berhasil merancang dan mengimplementasikan modul rekomendasi kos berbasis hybrid filtering yang menggabungkan atribut kos (harga, lokasi/jarak, tipe kos, fasilitas), profil penyewa, dan data interaksi pengguna, melalui tiga modul RecommendationService: ContentBasedFilter, CollaborativeFilter, dan SwitchingWeightedCombiner (Subbab 2.9.9, Gambar 4.4).

2. **Menjawab Perumusan Masalah poin 2** (strategi cold-start): Mekanisme switching hybrid terbukti dapat menangani kondisi cold-start secara otomatis dengan menetapkan alpha = 1 (Content-Based murni) ketika pengguna belum memiliki riwayat interaksi ber-rating, tanpa memerlukan intervensi manual (Subbab 2.2.3, KF-03 s.d. KF-06).
   `[ISI SETELAH EVALUASI]` -- lengkapi dengan angka Precision@K/Recall@K/NDCG/MAP hasil `EvaluationService::compareBaselines()` untuk skenario cold-start, dibandingkan baseline popularitas.

3. **Menjawab Perumusan Masalah poin 3** (perbandingan performa terhadap baseline): `[ISI SETELAH EVALUASI]` -- lengkapi dengan hasil `compareBaselines()` (hybrid vs popularitas/CB murni/CF murni) dan `compareAlphas()` (alpha optimal) pada K=5 dan K=10, untuk skenario warm-start maupun cold-start. Nyatakan secara eksplisit apakah model hybrid unggul, setara, atau kalah dibanding tiap baseline, dan pada metrik apa saja.

4. **Menjawab Perumusan Masalah poin 4** (prototipe antarmuka): Hasil rekomendasi berhasil ditampilkan melalui prototipe aplikasi Flutter dalam bentuk daftar Top-N kos beserta skor kecocokan dan alasan (badge match-percentage dan `generateExplanation`), sesuai arsitektur thin-client pada Subbab 2.9.1 dan 2.9.9.
   `[ISI SETELAH EVALUASI]` -- lengkapi dengan skor SUS rata-rata dari 150 responden dan kategorinya menurut Bangor et al. (mis. "Good", "Excellent"), serta ringkasan kepuasan terhadap kualitas rekomendasi.

## 5.2 Saran

Saran disusun untuk dua sasaran: pengembangan penelitian lanjutan, dan pengembangan sistem KOSKITA secara praktis.

**Untuk penelitian lanjutan:**
- `[ISI SETELAH EVALUASI]` -- jika ditemukan aspek profil/interaksi tertentu yang berkontribusi kecil terhadap akurasi (mis. dari `perQuestionBreakdown()` SUS atau analisis metrik per-K), sarankan eksplorasi atribut tambahan pada penelitian berikutnya.
- Eksplorasi pendekatan lanjutan (graph-based, deep learning) untuk skala data yang lebih besar dari skala prototipe penelitian ini (Subbab 2.2.3, paragraf penutup).
- Eksplorasi domain hunian sewa lain (apartemen, rumah kontrakan) yang disebut pada Subbab 1.4.2 sebagai peluang perluasan kontribusi teoretis.
- Melengkapi data peraturan kos (rules) yang saat ini belum tersedia dari sumber data yang dipakai, agar dimensi "peraturan kos" pada vektor Content-Based benar-benar berkontribusi pada perhitungan skor (bukan bernilai nol seragam).

**Untuk pengembangan sistem KOSKITA:**
- Mempertimbangkan Distance Matrix API (bukan garis lurus/haversine) untuk perhitungan jarak ke kampus yang lebih akurat mencerminkan jarak rute riil.
- Menambah sumber data kos dari platform lain (di luar Mamikos dan Rukita) untuk memperluas cakupan katalog di wilayah studi kasus.
- `[ISI SETELAH EVALUASI]` -- saran spesifik lain berdasarkan umpan balik kualitatif responden pada kuesioner SUS (pertanyaan tambahan kepuasan rekomendasi, Subbab 1.5.1).
