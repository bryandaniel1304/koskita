import 'dart:async';
import 'package:app_links/app_links.dart';
import 'package:go_router/go_router.dart';

/// Deep link -- buka layar tertentu langsung dari luar app (link dibagikan
/// lewat WhatsApp, atau App Link/Universal Link resmi). Dua bentuk tautan
/// didukung dan dinormalisasi jadi rute go_router yang sama:
///   - Skema kustom "koskita://kos/12" -- langsung berfungsi, tidak butuh
///     domain/verifikasi apapun.
///   - App Link https "https://domain-produksi/kos/12" -- baru aktif
///     kalau domain produksi & assetlinks.json sudah diisi benar, lihat
///     catatan di routes/web.php backend & AndroidManifest.xml.
///
/// Cuma rute "/kos/:id" yang didukung untuk sekarang (paling sering
/// dibagikan orang) -- pola yang sama gampang diperluas ke rute lain kalau
/// dibutuhkan nanti, tinggal tambah cabang di [_pathFromUri].
class DeepLinkService {
  static final AppLinks _appLinks = AppLinks();
  static StreamSubscription<Uri>? _sub;

  /// Dibaca sekali oleh SplashScreen sesudah auto-login berhasil, lalu
  /// dikosongkan -- supaya tautan cold-start cuma "dipakai" sekali, tidak
  /// nyangkut dipakai ulang tiap kali splash tampil lagi di sesi yang sama.
  static Future<String?> consumePendingInitialPath() async {
    try {
      final uri = await _appLinks.getInitialLink();
      return _pathFromUri(uri);
    } catch (_) {
      return null;
    }
  }

  /// Dengarkan tautan yang masuk SELAGI app sedang jalan (bukan cold
  /// start) -- mis. pengguna tap link dari notifikasi WhatsApp saat app
  /// masih di background. Navigasi langsung; kalau ternyata belum login,
  /// redirect bawaan go_router (lihat app_router.dart) yang lempar ke
  /// /login seperti biasa -- tautannya sendiri tidak disimpan buat dicoba
  /// lagi setelah login (di luar scope untuk sekarang).
  static void initialize(GoRouter router) {
    _sub ??= _appLinks.uriLinkStream.listen((uri) {
      final path = _pathFromUri(uri);
      if (path != null) router.go(path);
    });
  }

  static String? _pathFromUri(Uri? uri) {
    if (uri == null) return null;
    // "koskita://kos/12" -> host="kos", pathSegments=["12"].
    // "https://domain/kos/12" -> pathSegments=["kos","12"].
    // Disatukan jadi satu bentuk supaya logikanya tidak dobel.
    final segments = uri.scheme == 'koskita'
        ? [uri.host, ...uri.pathSegments]
        : uri.pathSegments;
    final cleaned = segments.where((s) => s.isNotEmpty).toList();
    if (cleaned.length >= 2 && cleaned[0] == 'kos' && int.tryParse(cleaned[1]) != null) {
      return '/kos/${cleaned[1]}';
    }
    return null;
  }
}
