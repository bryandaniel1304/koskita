import 'dart:convert';
import 'package:google_sign_in/google_sign_in.dart';
import 'api_service.dart';

/// Wrapper tipis di atas `google_sign_in` -- alur sign-in ASLI (native
/// Google Play Services/App Store) terjadi sepenuhnya di perangkat, service
/// ini cuma ambil ID token hasilnya. AuthProvider.loginWithGoogle() yang
/// kirim token itu ke backend untuk diverifikasi & ditukar jadi token
/// Sanctum -- backend TIDAK pernah melihat kredensial Google pengguna.
class GoogleAuthService {
  static GoogleSignIn? _instance;
  static String? _configuredClientId;

  /// false kalau admin belum mengisi GOOGLE_CLIENT_ID di backend -- dipakai
  /// layar Masuk/Daftar untuk sembunyikan tombol "Masuk dengan Google"
  /// daripada tampil tapi selalu gagal kalau ditekan (pola sama seperti
  /// WebAuthController::googleLoginConfigured() di web).
  static Future<bool> isConfigured() async {
    try {
      final response = await ApiService.get('/auth/google/config');
      final data = jsonDecode(response.body);
      if (data['configured'] == true && data['client_id'] != null) {
        _configuredClientId = data['client_id'];
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  /// null kalau pengguna membatalkan dialog akun, atau sign-in gagal --
  /// SENGAJA tidak melempar exception, supaya pemanggil cukup cek null
  /// tanpa perlu try/catch terpisah untuk kasus "batal" yang sangat wajar.
  static Future<({String idToken, String email})?> signIn() async {
    final clientId = _configuredClientId;
    if (clientId == null) return null;

    try {
      // serverClientId WAJIB diisi client ID "Web application" (bukan
      // client ID Android/iOS-nya sendiri) supaya idToken yang dihasilkan
      // punya "aud" yang cocok dengan yang diverifikasi backend -- lihat
      // AuthController::loginWithGoogle() & catatan setup di .env.
      _instance ??= GoogleSignIn(scopes: ['email'], serverClientId: clientId);
      final account = await _instance!.signIn();
      if (account == null) return null;

      final auth = await account.authentication;
      final idToken = auth.idToken;
      if (idToken == null) return null;

      return (idToken: idToken, email: account.email);
    } catch (_) {
      return null;
    }
  }

  /// Dipanggil bareng AuthProvider.logout() -- membersihkan sesi Google
  /// lokal di perangkat supaya `signIn()` berikutnya menampilkan dialog
  /// pilih akun lagi, bukan diam-diam masuk pakai akun Google yang sama.
  static Future<void> signOut() async {
    try {
      await _instance?.signOut();
    } catch (_) {
      // Abaikan -- ini cuma pembersihan sesi lokal, tidak memengaruhi
      // logout KosKita itu sendiri (yang sudah selesai lewat token Sanctum).
    }
  }
}
