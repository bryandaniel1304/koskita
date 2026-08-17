import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:shared_preferences/shared_preferences.dart';

/// Konfigurasi environment aplikasi.
///
/// Base URL bisa di-override tanpa mengubah kode lewat:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.5:8000/api
///
/// Ini dipakai untuk tes dari HP fisik di jaringan WiFi yang sama dengan
/// komputer yang menjalankan backend Laravel (XAMPP/`php artisan serve`),
/// karena `10.0.2.2`/`127.0.0.1` hanya bisa diakses dari emulator/mesin itu
/// sendiri, bukan dari perangkat lain di jaringan.
///
/// `--dart-define` cuma berlaku kalau app dijalankan lewat `flutter run`.
/// Kalau APK-nya di-install & dibuka langsung di HP (tanpa terminal), tidak
/// ada cara untuk lewat `--dart-define` lagi -- makanya ada [runtimeOverride]
/// di bawah: alamat server bisa diisi manual dari dalam app sendiri (lihat
/// tombol "Pengaturan Server" di layar Login) dan disimpan ke SharedPreferences,
/// supaya tidak perlu rebuild/rerun tiap kali IP komputer server berubah.
class AppConfig {
  static const String _overrideBaseUrl = String.fromEnvironment('API_BASE_URL');
  static const _runtimeOverrideKey = 'dev_api_base_url_override';

  static String? _runtimeOverride;

  /// Dipanggil sekali di `main()` sebelum `runApp`, supaya override
  /// tersimpan sudah siap dipakai request API pertama (splash/auto-login).
  static Future<void> loadRuntimeOverride() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_runtimeOverrideKey);
    _runtimeOverride = (saved != null && saved.isNotEmpty) ? saved : null;
  }

  /// Simpan (atau hapus, kalau [url] null/kosong) alamat server pilihan
  /// pengguna. Berlaku seketika untuk request-request berikutnya.
  static Future<void> setRuntimeOverride(String? url) async {
    final prefs = await SharedPreferences.getInstance();
    final trimmed = url?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      _runtimeOverride = null;
      await prefs.remove(_runtimeOverrideKey);
      return;
    }
    _runtimeOverride = trimmed;
    await prefs.setString(_runtimeOverrideKey, trimmed);
  }

  /// Alamat server yang sedang aktif dipakai -- ditampilkan di dialog
  /// "Pengaturan Server" supaya pengguna tahu apa yang sedang terisi.
  static String get currentOverride => _runtimeOverride ?? '';

  static String get apiBaseUrl {
    if (_overrideBaseUrl.isNotEmpty) {
      return _overrideBaseUrl;
    }
    if (_runtimeOverride != null && _runtimeOverride!.isNotEmpty) {
      return _runtimeOverride!;
    }
    return _defaultBaseUrl;
  }

  static String get _defaultBaseUrl {
    if (!kIsWeb && Platform.isAndroid) {
      // Alias bawaan Android Emulator untuk mengakses localhost komputer host.
      // PERINGATAN: alamat ini TIDAK bisa dijangkau dari HP fisik -- kalau
      // app dibuka di HP sungguhan dan login "server tidak merespons" terus,
      // ini penyebab paling umum. Isi alamat IP LAN komputer server lewat
      // "Pengaturan Server" di layar Login.
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }

  /// Base URL server TANPA sufiks `/api` -- dipakai untuk buka halaman web
  /// biasa (mis. Kebijakan Privasi/Syarat & Ketentuan), bukan panggilan API.
  static String get webBaseUrl => apiBaseUrl.replaceFirst(RegExp(r'/api/?$'), '');
}
