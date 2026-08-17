import 'package:home_widget/home_widget.dart';
import '../models/kos.dart';

/// Jembatan Flutter -> widget layar utama Android "Kos Terakhir Dilihat"
/// (lihat KosWidgetProvider.kt). iOS sengaja tidak diikutkan -- widget
/// iOS (WidgetKit) butuh target Extension terpisah yang cuma bisa dibuat
/// lewat Xcode, di luar scope yang bisa diverifikasi dari sini.
class AppWidgetSyncService {
  static const _androidProviderName = 'KosWidgetProvider';

  /// Dipanggil tiap kali ada kos baru dibuka (lihat KosProvider) -- widget
  /// di home screen (kalau sudah dipasang pengguna) langsung ikut ter-update
  /// tanpa perlu buka app.
  static Future<void> updateLastViewed(Kos kos) async {
    try {
      await HomeWidget.saveWidgetData<int>('kos_id', kos.id);
      await HomeWidget.saveWidgetData<String>('kos_name', kos.name);
      await HomeWidget.saveWidgetData<String>('kos_price', 'Rp ${(kos.price / 1000000).toStringAsFixed(1)} jt/bln');
      await HomeWidget.updateWidget(androidName: _androidProviderName);
    } catch (_) {
      // Widget cuma pemanis -- gagal update tidak boleh ganggu alur utama.
      // Juga aman kalau dipanggil di HP yang tidak mendukung home screen
      // widget sama sekali (mis. sebagian launcher custom).
    }
  }

  /// Dipanggil saat logout -- kosongkan widget supaya tidak menampilkan
  /// riwayat akun sebelumnya ke pengguna berikutnya di HP yang sama.
  static Future<void> clear() async {
    try {
      await HomeWidget.saveWidgetData<int>('kos_id', -1);
      await HomeWidget.saveWidgetData<String>('kos_name', null);
      await HomeWidget.saveWidgetData<String>('kos_price', null);
      await HomeWidget.updateWidget(androidName: _androidProviderName);
    } catch (_) {
      // Idem -- nice to have.
    }
  }
}
