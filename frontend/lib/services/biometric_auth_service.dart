import 'package:flutter/foundation.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Login sidik jari/wajah -- MURNI gerbang lokal di HP (bukan pengganti
/// sesi API). Token tetap tersimpan seperti biasa di flutter_secure_storage;
/// biometrik cuma dipakai untuk "buka kunci" sesi yang sudah ada supaya
/// pengguna tidak perlu ketik ulang password tiap buka aplikasi.
class BiometricAuthService {
  static final LocalAuthentication _auth = LocalAuthentication();
  static const _prefsKey = 'biometric_unlock_enabled_v1';

  /// Perangkat punya sensor sidik jari/wajah DAN pengguna sudah daftarkan
  /// minimal satu (mis. bukan HP tanpa sensor sama sekali, atau sensor ada
  /// tapi belum pernah didaftarkan sidik jarinya).
  static Future<bool> isDeviceSupported() async {
    try {
      final canCheck = await _auth.canCheckBiometrics;
      final isSupported = await _auth.isDeviceSupported();
      if (!canCheck && !isSupported) return false;
      final available = await _auth.getAvailableBiometrics();
      return available.isNotEmpty || isSupported;
    } catch (e) {
      debugPrint('BiometricAuthService isDeviceSupported gagal: $e');
      return false;
    }
  }

  static Future<bool> isEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_prefsKey) ?? false;
  }

  static Future<void> setEnabled(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsKey, enabled);
  }

  /// Minta autentikasi biometrik. Return false (bukan exception) kalau
  /// dibatalkan/gagal/tidak didukung -- pemanggil cukup cek boolean,
  /// tanpa perlu try/catch sendiri di tiap layar.
  static Future<bool> authenticate({String reason = 'Konfirmasi identitasmu untuk membuka KosKita'}) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          biometricOnly: false, // izinkan fallback ke PIN/pola perangkat kalau sensor gagal berkali-kali
          stickyAuth: true,
        ),
      );
    } catch (e) {
      debugPrint('BiometricAuthService authenticate gagal: $e');
      return false;
    }
  }
}
