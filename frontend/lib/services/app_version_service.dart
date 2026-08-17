import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Info versi app (ditampilkan di halaman Profil) + deteksi "baru saja
/// update" untuk memicu dialog "Yang Baru" -- lihat ChangelogSheet.
class AppVersionService {
  static const _lastSeenVersionKey = 'last_seen_app_version_v1';

  /// Catatan rilis per versi -- entri baru ditambah tiap kali ada rilis
  /// baru, KEY-nya harus persis sama dengan `version` di pubspec.yaml
  /// (bagian sebelum tanda "+"). Sengaja di-hardcode di sini (bukan dari
  /// API) -- murah dibuat & tidak butuh endpoint backend baru.
  static const Map<String, List<String>> changelog = {
    '1.0.0': [
      'Login lebih cepat dengan sidik jari/wajah.',
      'Tetap bisa jelajahi kos yang pernah dilihat walau lagi offline.',
      'Pengingat otomatis mendekati jatuh tempo sewa.',
      'Widget layar utama "Kos Terakhir Dilihat" (Android).',
    ],
  };

  static Future<PackageInfo> info() => PackageInfo.fromPlatform();

  static Future<String> versionLabel() async {
    final i = await info();
    return 'v${i.version} (build ${i.buildNumber})';
  }

  /// Cek apakah perlu tampil dialog "Yang Baru". Balikin null kalau tidak
  /// perlu -- baik karena ini instalasi pertama kali (belum ada versi
  /// "terakhir dilihat" tersimpan, jadi bukan hasil update) maupun karena
  /// versinya memang belum berubah sejak terakhir dicek.
  static Future<List<String>?> checkForChangelog() async {
    final i = await info();
    final prefs = await SharedPreferences.getInstance();
    final lastSeen = prefs.getString(_lastSeenVersionKey);
    await prefs.setString(_lastSeenVersionKey, i.version);

    if (lastSeen == null || lastSeen == i.version) return null;
    return changelog[i.version];
  }
}
