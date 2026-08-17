import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../models/user.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';
import '../services/notification_scheduler.dart';
import '../services/app_shortcuts_service.dart';
import '../services/app_badge_service.dart';
import '../services/app_widget_sync_service.dart';
import '../services/reverb_service.dart';
import '../services/google_auth_service.dart';
import '../services/fcm_service.dart';

/// Hasil percobaan login -- dibedakan dari sekadar bool supaya UI tahu
/// harus lempar ke layar kode verifikasi 2FA, bukan langsung dianggap
/// gagal/berhasil.
enum LoginResult { success, requiresTwoFactor, failed }

class AuthProvider with ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  String? _token;
  String? _errorMessage;
  /// Email yang lagi nunggu kode 2FA -- diisi begitu login() balas
  /// requiresTwoFactor, dipakai verifyTwoFactorCode()/resendTwoFactorCode()
  /// supaya layar kode tidak perlu terima ulang emailnya lewat parameter.
  String? _pendingTwoFactorEmail;

  AuthProvider() {
    ApiService.onUnauthorized = _handleUnauthorized;
  }

  User? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _token != null;
  String? get token => _token;
  String? get errorMessage => _errorMessage;
  String? get pendingTwoFactorEmail => _pendingTwoFactorEmail;

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  void _handleUnauthorized() {
    if (_token == null) return;
    _token = null;
    _user = null;
    unawaited(AppShortcutsService.syncShortcuts(null));
    unawaited(AppBadgeService.clear());
    unawaited(AppWidgetSyncService.clear());
    ReverbService.disconnect();
    unawaited(FcmService.unregisterCurrentToken());
    notifyListeners();
  }

  Future<void> tryAutoLogin() async {
    final token = await ApiService.getToken();
    if (token == null) return;

    try {
      final response = await ApiService.get('/profile');
      final userData = jsonDecode(response.body);
      _token = token;
      _user = User.fromJson(userData);
      unawaited(AppShortcutsService.syncShortcuts(_user!.role));
      unawaited(ReverbService.connect(_user!.id));
      unawaited(FcmService.registerCurrentToken());
      notifyListeners();
    } on ApiException catch (_) {
      // Token tidak valid lagi / server tidak terjangkau saat startup.
      _token = null;
      _user = null;
    } catch (_) {
      // Respons tak terduga -- jangan biarkan splash nyangkut, anggap
      // saja auto-login gagal & lanjut ke alur login manual.
      _token = null;
      _user = null;
    }
  }

  /// Sinkronkan ulang data user (mis. status verifikasi email) dari server
  /// tanpa risiko logout paksa kalau request gagal/timeout -- dipakai saat
  /// app kembali aktif (setelah user buka link verifikasi di Gmail lalu
  /// balik ke app) atau pull-to-refresh manual di layar Profil.
  Future<bool> refreshUser() async {
    if (_token == null) return false;
    try {
      final response = await ApiService.get('/profile');
      final userData = jsonDecode(response.body);
      _user = User.fromJson(userData);
      notifyListeners();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Kirim dokumen identitas untuk badge "Pemilik Terverifikasi" -- ditinjau
  /// admin, hasilnya dilihat lewat refreshUser() setelah dikirim.
  Future<bool> submitOwnerVerification(XFile document) async {
    try {
      await ApiService.multipart('POST', '/owner/verification', {}, photos: [document], fileFieldName: 'document');
      await refreshUser();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> uploadQris(XFile qris) async {
    try {
      await ApiService.multipart('POST', '/owner/qris', {}, photos: [qris], fileFieldName: 'qris');
      await refreshUser();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteQris() async {
    try {
      await ApiService.delete('/owner/qris');
      await refreshUser();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Foto profil -- dipakai penyewa MAUPUN pemilik (beda dari QRIS/
  /// verifikasi yang khusus pemilik).
  Future<bool> uploadAvatar(XFile avatar) async {
    try {
      await ApiService.multipart('POST', '/profile/avatar', {}, photos: [avatar], fileFieldName: 'avatar');
      await refreshUser();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteAvatar() async {
    try {
      await ApiService.delete('/profile/avatar');
      await refreshUser();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<LoginResult> login(String email, String password) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/login', {
        'email': email,
        'password': password,
      });
      final data = jsonDecode(response.body);

      if (data['requires_2fa'] == true) {
        _pendingTwoFactorEmail = email;
        return LoginResult.requiresTwoFactor;
      }

      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      // Fire-and-forget -- minta izin notifikasi setelah login berhasil,
      // bukan di awal buka app, supaya konteksnya jelas ("KosKita mau
      // ingatkan jatuh tempo sewa"), bukan izin acak tanpa alasan.
      unawaited(NotificationScheduler.requestPermission());
      unawaited(AppShortcutsService.syncShortcuts(_user!.role));
      unawaited(ReverbService.connect(_user!.id));
      unawaited(FcmService.registerCurrentToken());
      return LoginResult.success;
    } on ApiException catch (e) {
      _errorMessage = e.type == ApiErrorType.unknown
          ? 'Email atau password salah.'
          : e.message;
      return LoginResult.failed;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
      return LoginResult.failed;
    } finally {
      _setLoading(false);
    }
  }

  /// [email] didapat langsung dari akun Google yang dipilih di perangkat
  /// (GoogleAuthService.signIn()) -- disimpan sebagai _pendingTwoFactorEmail
  /// kalau ternyata akun itu punya 2FA aktif, PERSIS pola yang sama dengan
  /// login() biasa (email yang sudah diketahui pemanggil, bukan dari
  /// respons server, karena respons requires_2fa memang tidak membawanya).
  Future<LoginResult> loginWithGoogle(String idToken, String email) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/auth/google', {'id_token': idToken});
      final data = jsonDecode(response.body);

      if (data['requires_2fa'] == true) {
        _pendingTwoFactorEmail = email;
        return LoginResult.requiresTwoFactor;
      }

      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      unawaited(NotificationScheduler.requestPermission());
      unawaited(AppShortcutsService.syncShortcuts(_user!.role));
      unawaited(ReverbService.connect(_user!.id));
      unawaited(FcmService.registerCurrentToken());
      return LoginResult.success;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return LoginResult.failed;
    } catch (_) {
      _errorMessage = 'Gagal masuk dengan Google. Coba lagi.';
      return LoginResult.failed;
    } finally {
      _setLoading(false);
    }
  }

  /// Tukar kode OTP 2FA dengan token beneran -- login() harus balas
  /// requiresTwoFactor dulu sebelum ini bisa dipanggil.
  Future<bool> verifyTwoFactorCode(String code) async {
    final email = _pendingTwoFactorEmail;
    if (email == null) return false;

    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/verify-2fa', {'email': email, 'code': code});
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      _pendingTwoFactorEmail = null;
      unawaited(NotificationScheduler.requestPermission());
      unawaited(AppShortcutsService.syncShortcuts(_user!.role));
      unawaited(ReverbService.connect(_user!.id));
      unawaited(FcmService.registerCurrentToken());
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return false;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> resendTwoFactorCode() async {
    final email = _pendingTwoFactorEmail;
    if (email == null) return false;
    try {
      await ApiService.post('/2fa/resend', {'email': email});
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Batal di tengah jalan (mis. tekan "Kembali" di layar kode) -- supaya
  /// percobaan login berikutnya mulai bersih, bukan nyangkut nunggu kode
  /// dari email yang sudah ditinggalkan.
  void cancelTwoFactorChallenge() {
    _pendingTwoFactorEmail = null;
    notifyListeners();
  }

  /// Trio toggle 2FA dari Profil > Keamanan -- pola sama dengan alur
  /// aktivasi di web: kirim kode dulu buat buktikan email bisa diakses,
  /// baru benar-benar aktif setelah kodenya benar. Nonaktifkan langsung
  /// (sudah login, tidak perlu bukti tambahan).
  Future<bool> startEnableTwoFactor() async {
    try {
      await ApiService.post('/2fa/enable/start', {});
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> confirmEnableTwoFactor(String code) async {
    try {
      await ApiService.post('/2fa/enable/confirm', {'code': code});
      await refreshUser();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> disableTwoFactor() async {
    try {
      await ApiService.post('/2fa/disable', {});
      await refreshUser();
      return true;
    } catch (_) {
      return false;
    }
  }

  /// Satu switch = mematikan email DAN push (FCM) sekaligus untuk jenis
  /// itu -- lihat backend AuthController::updateNotificationPreferences().
  Future<bool> updateNotificationPreferences({
    required bool notifyBookings,
    required bool notifyMessages,
    required bool notifyWaitlist,
  }) async {
    try {
      await ApiService.put('/profile/notification-preferences', {
        'notify_bookings': notifyBookings,
        'notify_messages': notifyMessages,
        'notify_waitlist': notifyWaitlist,
      });
      await refreshUser();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> register(String name, String email, String phone, String password, String role) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/register', {
        'name': name,
        'email': email,
        'phone': phone,
        'password': password,
        'password_confirmation': password,
        'role': role,
      });
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      unawaited(NotificationScheduler.requestPermission());
      unawaited(AppShortcutsService.syncShortcuts(_user!.role));
      unawaited(ReverbService.connect(_user!.id));
      unawaited(FcmService.registerCurrentToken());
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return false;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<void> logout() async {
    _setLoading(true);
    try {
      await ApiService.post('/logout', {});
    } on ApiException catch (_) {
      // Tetap lanjut hapus token lokal walau request logout gagal.
    } catch (_) {
      // Idem -- token lokal tetap harus dihapus di bawah.
    }
    await ApiService.removeToken();
    _token = null;
    _user = null;
    unawaited(AppShortcutsService.syncShortcuts(null));
    unawaited(AppBadgeService.clear());
    unawaited(AppWidgetSyncService.clear());
    ReverbService.disconnect();
    unawaited(FcmService.unregisterCurrentToken());
    unawaited(GoogleAuthService.signOut());
    _setLoading(false);
  }

  Future<bool> updateProfile(Map<String, dynamic> profileData) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      await ApiService.post('/profile', profileData);
      await tryAutoLogin();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return false;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
      return false;
    } finally {
      _setLoading(false);
    }
  }

  Future<bool> resendVerificationEmail() async {
    try {
      await ApiService.post('/email/resend', {});
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> resetInteractions() async {
    _setLoading(true);
    try {
      await ApiService.post('/profile/reset-interactions', {});
      await tryAutoLogin();
      return true;
    } on ApiException catch (_) {
      // Abaikan; UI cukup tahu operasi gagal lewat return false.
      return false;
    } catch (_) {
      return false;
    } finally {
      _setLoading(false);
    }
  }
}
