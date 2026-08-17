import 'package:go_router/go_router.dart';
import 'package:quick_actions/quick_actions.dart';

/// Shortcut tekan-lama ikon aplikasi (long-press app icon -> menu cepat),
/// dikenal sebagai "App Shortcuts" di Android dan "Quick Actions" di iOS.
///
/// Daftarnya beda tergantung peran (penyewa vs pemilik kos) karena rute
/// yang relevan juga beda -- disinkronkan ulang tiap kali status login
/// berubah (login/register/logout/auto-login), BUKAN cuma sekali saat
/// app pertama dibuka, supaya tidak nyangkut nampilin menu peran lama
/// kalau user logout lalu login lagi dengan akun peran lain.
class AppShortcutsService {
  static const _instance = QuickActions();
  static bool _initialized = false;

  /// Dipanggil sekali di `main()` -- pasang handler yang menerjemahkan tap
  /// pada shortcut jadi navigasi `go_router`. Redirect logic router sendiri
  /// (lihat `app_router.dart`) yang menjaga kalau ternyata sesi sudah
  /// habis/role tidak cocok saat shortcut ditekan (mis. app baru dibuka
  /// dari kondisi ter-kill lalu auto-login gagal).
  static Future<void> initialize(GoRouter router) async {
    if (_initialized) return;
    _initialized = true;
    await _instance.initialize((type) {
      final path = _routeForType(type);
      if (path != null) router.go(path);
    });
  }

  static String? _routeForType(String type) {
    switch (type) {
      case 'tenant_search':
        return '/home';
      case 'tenant_favorites':
        return '/favorites';
      case 'tenant_bookings':
        return '/bookings';
      case 'owner_koses':
        return '/owner/koses';
      case 'owner_bookings':
        return '/owner/bookings';
      default:
        return null;
    }
  }

  /// Set ulang daftar shortcut sesuai peran. `role` null berarti belum/tidak
  /// login lagi -- shortcut dikosongkan supaya tidak mengarahkan ke layar
  /// yang butuh sesi yang sudah tidak ada.
  static Future<void> syncShortcuts(String? role) async {
    try {
      if (role == 'owner') {
        await _instance.setShortcutItems(const [
          ShortcutItem(type: 'owner_koses', localizedTitle: 'Kos Saya', icon: 'ic_shortcut_home'),
          ShortcutItem(type: 'owner_bookings', localizedTitle: 'Booking Masuk', icon: 'ic_shortcut_booking'),
        ]);
      } else if (role != null) {
        await _instance.setShortcutItems(const [
          ShortcutItem(type: 'tenant_search', localizedTitle: 'Cari Kos', icon: 'ic_shortcut_search'),
          ShortcutItem(type: 'tenant_favorites', localizedTitle: 'Favorit', icon: 'ic_shortcut_favorite'),
          ShortcutItem(type: 'tenant_bookings', localizedTitle: 'Booking Saya', icon: 'ic_shortcut_booking'),
        ]);
      } else {
        await _instance.clearShortcutItems();
      }
    } catch (_) {
      // Shortcut cuma pemanis navigasi -- gagal set tidak boleh ganggu alur utama.
    }
  }
}
