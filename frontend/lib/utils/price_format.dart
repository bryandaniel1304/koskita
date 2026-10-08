/// Harga kos dalam format ringkas yang sama di semua layar: "Rp 1.6 jt"
/// untuk jutaan, "Rp 650 rb" untuk ratusan ribu. Pemanggil menambahkan
/// satuannya sendiri ("/bln", "/bulan") sesuai konteks tampilan.
///
/// Sebelumnya tiap layar punya formatter sendiri dengan aturan berbeda,
/// sehingga kos di bawah satu juta tampil mentah ("Rp 500000/bln") di
/// daftar tapi "Rp 0.5 jt/bln" di perbandingan.
String formatKosPrice(int price) {
  if (price >= 1000000) {
    return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt';
  }
  if (price >= 1000) {
    return 'Rp ${(price / 1000).round()} rb';
  }
  return 'Rp $price';
}
