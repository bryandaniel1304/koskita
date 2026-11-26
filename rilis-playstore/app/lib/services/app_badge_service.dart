import 'package:app_badge_plus/app_badge_plus.dart';

/// Badge angka merah di ikon aplikasi (home screen launcher) -- dukungannya
/// tergantung launcher/OS (Samsung, Huawei, iOS semua didukung; sebagian
/// launcher custom di Android tidak). [AppBadgePlus.isSupported] dicek
/// sekali dan di-cache supaya tidak perlu channel call berulang tiap update.
class AppBadgeService {
  static bool? _supported;

  static Future<void> setCount(int count) async {
    try {
      _supported ??= await AppBadgePlus.isSupported();
      if (_supported != true) return;
      await AppBadgePlus.updateBadge(count);
    } catch (_) {
      // Badge cuma pemanis -- gagal update tidak boleh ganggu alur utama.
    }
  }

  static Future<void> clear() => setCount(0);
}
