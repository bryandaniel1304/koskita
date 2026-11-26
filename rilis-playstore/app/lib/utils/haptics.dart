import 'package:flutter/services.dart';

/// Getaran halus di aksi-aksi penting -- bukan dekorasi, ini yang bikin
/// sentuhan di aplikasi "premium" terasa solid dibanding yang polos.
/// Dipusatkan di sini (bukan panggil `HapticFeedback` langsung tersebar di
/// tiap layar) supaya jenis getaran per kategori aksi konsisten di seluruh
/// app, dan gampang dimatikan/diganti sekaligus kalau perlu nanti.
class Haptics {
  Haptics._();

  /// Pilihan ringan -- tap pada opsi kecil yang tidak final (mis. tiap
  /// bintang rating yang disentuh, ganti tab).
  static void selection() => HapticFeedback.selectionClick();

  /// Aksi ringan yang langsung berefek tapi gampang dibalik (favorit,
  /// kirim pesan, bookmark).
  static void light() => HapticFeedback.lightImpact();

  /// Aksi yang "menyelesaikan sesuatu" -- submit booking, konfirmasi,
  /// berhasil kirim laporan.
  static void success() => HapticFeedback.mediumImpact();

  /// Aksi gagal/ditolak -- validasi gagal, request error.
  static void error() => HapticFeedback.heavyImpact();
}
