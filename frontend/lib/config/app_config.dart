import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;

/// Konfigurasi environment aplikasi.
///
/// Base URL bisa di-override tanpa mengubah kode lewat:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.5:8000/api
///
/// Ini dipakai untuk tes dari HP fisik di jaringan WiFi yang sama dengan
/// komputer yang menjalankan backend Laravel (XAMPP/`php artisan serve`),
/// karena `10.0.2.2`/`127.0.0.1` hanya bisa diakses dari emulator/mesin itu
/// sendiri, bukan dari perangkat lain di jaringan.
class AppConfig {
  static const String _overrideBaseUrl = String.fromEnvironment('API_BASE_URL');

  static String get apiBaseUrl {
    if (_overrideBaseUrl.isNotEmpty) {
      return _overrideBaseUrl;
    }
    return _defaultBaseUrl;
  }

  static String get _defaultBaseUrl {
    if (!kIsWeb && Platform.isAndroid) {
      // Alias bawaan Android Emulator untuk mengakses localhost komputer host.
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }
}
