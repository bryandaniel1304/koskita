/// Konfigurasi environment -- VERSI RILIS PLAY STORE.
///
/// Beda dengan aplikasi skripsi (folder `frontend/`), alamat server di sini
/// DIKUNCI ke backend produksi dan tidak bisa diubah dari dalam aplikasi:
/// tidak ada tombol "Pengaturan Server", tidak ada alamat 10.0.2.2/LAN.
///
/// Alamat diisi saat build lewat skrip `rilis-playstore/build-aab.(sh|bat)`:
///   flutter build appbundle --dart-define=API_BASE_URL=https://.../api
///
/// Kalau lupa diisi, dipakai [_produksiBaseUrl] di bawah -- GANTI nilainya
/// dengan domain produksi sebelum build pertama (lihat PANDUAN-PLAYSTORE.md).
class AppConfig {
  static const String _overrideBaseUrl = String.fromEnvironment('API_BASE_URL');

  /// Alamat API backend PRODUKSI (instance terpisah dari server skripsi,
  /// database-nya hanya berisi kos klien yang sudah diverifikasi).
  static const String _produksiBaseUrl = 'https://GANTI-domain-produksi/api';

  /// Dipertahankan supaya pemanggil lama (main.dart) tetap jalan; di versi
  /// rilis tidak ada lagi alamat server yang bisa diubah pengguna.
  static Future<void> loadRuntimeOverride() async {}

  static Future<void> setRuntimeOverride(String? url) async {}

  static String get currentOverride => '';

  /// Selalu true di versi rilis -- menyembunyikan tombol "Pengaturan Server"
  /// di layar Login.
  static bool get isServerFixed => true;

  static String get apiBaseUrl =>
      _overrideBaseUrl.isNotEmpty ? _overrideBaseUrl : _produksiBaseUrl;

  /// Base URL server TANPA sufiks `/api` -- dipakai untuk buka halaman web
  /// biasa (Kebijakan Privasi, Syarat & Ketentuan, Hapus Akun).
  static String get webBaseUrl => apiBaseUrl.replaceFirst(RegExp(r'/api/?$'), '');
}
