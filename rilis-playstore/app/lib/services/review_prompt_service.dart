import 'package:in_app_review/in_app_review.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Minta rating Play Store/App Store -- SENGAJA cuma dipicu sekali seumur
/// akun (bukan tiap kali momen positif terulang), di momen yang benar-benar
/// positif dan terukur: booking pertama pengguna berstatus "confirmed".
/// Bukan minta rating acak begitu app dibuka, yang malah kesannya murahan
/// dan gampang bikin pengguna kesal lalu kasih rating jelek.
class ReviewPromptService {
  static const _shownKey = 'review_prompt_shown_v1';

  /// Panggil tiap kali daftar booking berhasil di-fetch -- no-op diam-diam
  /// kalau sudah pernah diminta sebelumnya atau tidak ada booking yang
  /// confirmed.
  static Future<void> maybePromptAfterConfirmedBooking(bool hasConfirmedBooking) async {
    if (!hasConfirmedBooking) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getBool(_shownKey) ?? false) return;

      final inAppReview = InAppReview.instance;
      if (!await inAppReview.isAvailable()) return;

      // Tandai "sudah diminta" SEBELUM benar-benar minta -- OS sendiri
      // sudah punya kuota/pembatasan internal (tidak selalu benar-benar
      // menampilkan dialog), jadi tidak ada gunanya coba lagi di sesi
      // berikutnya kalau kesempatan pertama ini sudah dipakai.
      await prefs.setBool(_shownKey, true);
      await inAppReview.requestReview();
    } catch (_) {
      // "Nice to have" -- gagal minta rating tidak boleh ganggu alur utama.
    }
  }
}
