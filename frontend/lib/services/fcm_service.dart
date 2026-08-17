import 'dart:async';
import 'dart:io' show Platform;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'api_service.dart';

/// Push notification asli lewat Firebase Cloud Messaging.
///
/// SETIAP langkah di sini dibungkus try/catch karena project ini BELUM
/// (dan sampai admin melengkapi sendiri, tidak akan pernah) punya file
/// konfigurasi Firebase asli -- `google-services.json` (Android) /
/// `GoogleService-Info.plist` (iOS) -- ATAU plugin Gradle
/// `com.google.gms.google-services` diterapkan di
/// android/app/build.gradle.kts. KEDUANYA harus ditambahkan MANUAL oleh
/// yang mengelola project ini (lihat catatan setup lengkap di
/// backend/.env, bagian FIREBASE_*), tidak bisa dibuat dari lingkungan
/// pengembangan ini karena butuh project Firebase sungguhan.
///
/// Tanpa langkah itu, initialize() gagal diam-diam di baris
/// Firebase.initializeApp() (tidak ada aplikasi [DEFAULT] terdaftar) --
/// aplikasi tetap berjalan normal, cuma tanpa push instan. Persis pola
/// yang sama dengan ReverbService kalau server Reverb tidak jalan.
class FcmService {
  static bool _initialized = false;

  /// Dipanggil dari MessageProvider/NotificationProvider di main.dart
  /// (lihat pola ApiService.onUnauthorized) supaya badge langsung ter-
  /// update begitu push masuk SELAGI app terbuka -- FCM otomatis
  /// menampilkan notifikasi sistem sendiri kalau app di background/
  /// tertutup, tapi TIDAK kalau lagi dibuka (perilaku bawaan
  /// Android/iOS), jadi badge di dalam app perlu direfresh manual di sini.
  static void Function(Map<String, dynamic> data)? onForegroundMessage;

  static Future<void> initialize() async {
    if (_initialized || kIsWeb) return;
    try {
      await Firebase.initializeApp();
      _initialized = true;

      FirebaseMessaging.instance.onTokenRefresh.listen((token) {
        unawaited(_registerToken(token));
      });
      FirebaseMessaging.onMessage.listen((message) {
        onForegroundMessage?.call(message.data);
      });
    } catch (_) {
      // Firebase belum dikonfigurasi (lihat catatan kelas ini) -- diam
      // saja, fitur push cuma tidak aktif, tidak ada yang crash.
    }
  }

  /// Dipanggil setelah login berhasil (lihat AuthProvider) -- daftarkan
  /// token perangkat SAAT INI ke akun yang baru login. Aman dipanggil
  /// berkali-kali (backend upsert by token, lihat FcmTokenController).
  static Future<void> registerCurrentToken() async {
    if (!_initialized) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _registerToken(token);
    } catch (_) {
      // Firebase belum benar-benar terkonfigurasi -- diam saja.
    }
  }

  static Future<void> _registerToken(String token) async {
    try {
      await ApiService.post('/fcm-token', {
        'token': token,
        'device_type': (!kIsWeb && Platform.isIOS) ? 'ios' : 'android',
      });
    } catch (_) {
      // Belum login / backend tidak terjangkau -- token akan dicoba
      // didaftarkan lagi lain kali (onTokenRefresh, atau login berikutnya).
    }
  }

  /// Dipanggil saat logout -- HP ini berhenti terima push untuk akun yang
  /// baru saja keluar (device lain milik akun ini, kalau ada, tetap aktif).
  static Future<void> unregisterCurrentToken() async {
    if (!_initialized) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) {
        await ApiService.delete('/fcm-token', {'token': token});
      }
    } catch (_) {
      // Idem -- gagal diam-diam, baris fcm_tokens ini paling apes cuma
      // jadi basi (tidak berbahaya, cuma tidak dibersihkan tepat waktu).
    }
  }
}
