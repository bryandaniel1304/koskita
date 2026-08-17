import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:screen_protector/screen_protector.dart';
import '../../providers/auth_provider.dart';
import '../../utils/legal_links.dart';
import '../../utils/image_source_picker.dart';
import '../../widgets/theme_mode_selector.dart';
import '../../widgets/biometric_toggle.dart';
import '../../widgets/two_factor_toggle.dart';
import '../../widgets/notification_preferences_toggle.dart';
import '../../widgets/app_version_label.dart';
import '../../widgets/user_avatar.dart';
import '../../config/app_theme.dart';

class OwnerProfileScreen extends StatefulWidget {
  const OwnerProfileScreen({super.key});

  @override
  State<OwnerProfileScreen> createState() => _OwnerProfileScreenState();
}

class _OwnerProfileScreenState extends State<OwnerProfileScreen> {
  bool _isResending = false;
  bool _isUploadingVerification = false;
  bool _isUploadingQris = false;
  bool _isUploadingAvatar = false;

  Future<void> _changeAvatar(AuthProvider authProvider) async {
    final picked = await pickImageWithSourceChoice(context, title: 'Ganti Foto Profil');
    if (picked == null || !mounted) return;

    setState(() => _isUploadingAvatar = true);
    final ok = await authProvider.uploadAvatar(picked);
    if (!mounted) return;
    setState(() => _isUploadingAvatar = false);
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal mengunggah foto profil. Coba lagi.'), backgroundColor: AppTheme.danger),
      );
    }
  }

  Future<void> _removeAvatar(AuthProvider authProvider) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus Foto Profil'),
        content: const Text('Foto profilmu akan diganti kembali ke lingkaran inisial nama.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Hapus')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _isUploadingAvatar = true);
    await authProvider.deleteAvatar();
    if (!mounted) return;
    setState(() => _isUploadingAvatar = false);
  }

  Future<void> _pickAndSubmitVerification(AuthProvider authProvider) async {
    final image = await pickImageWithSourceChoice(context, title: 'Foto Dokumen Identitas');
    if (image == null || !mounted) return;
    setState(() => _isUploadingVerification = true);
    final success = await authProvider.submitOwnerVerification(image);
    if (!mounted) return;
    setState(() => _isUploadingVerification = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Dokumen terkirim, menunggu peninjauan admin.' : 'Gagal mengirim dokumen. Coba lagi.')),
    );
  }

  Future<void> _pickAndUploadQris(AuthProvider authProvider) async {
    final image = await pickImageWithSourceChoice(context, title: 'Foto Kode QRIS');
    if (image == null || !mounted) return;
    setState(() => _isUploadingQris = true);
    final success = await authProvider.uploadQris(image);
    if (!mounted) return;
    setState(() => _isUploadingQris = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Kode QRIS berhasil disimpan.' : 'Gagal menyimpan QRIS. Coba lagi.')),
    );
  }

  Future<void> _deleteQris(AuthProvider authProvider) async {
    final success = await authProvider.deleteQris();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Kode QRIS dihapus.' : 'Gagal menghapus QRIS.')),
    );
  }

  @override
  void initState() {
    super.initState();
    // Layar ini nampilin dokumen identitas & kode QRIS pembayaran milik
    // pemilik -- cukup sensitif untuk dicegah screenshot/perekaman layar
    // (FLAG_SECURE di Android, dicabut lagi di dispose() begitu keluar
    // dari layar ini supaya tidak "bocor" ke layar lain).
    ScreenProtector.preventScreenshotOn();
    // Sinkronkan status verifikasi terbaru begitu layar Profil dibuka
    // (mis. setelah user balik dari klik link verifikasi di Gmail).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<AuthProvider>(context, listen: false).refreshUser();
    });
  }

  @override
  void dispose() {
    ScreenProtector.preventScreenshotOff();
    super.dispose();
  }

  Future<void> _resendVerification(AuthProvider authProvider) async {
    setState(() => _isResending = true);
    final success = await authProvider.resendVerificationEmail();
    if (!mounted) return;
    setState(() => _isResending = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success ? 'Email verifikasi telah dikirim ulang. Cek inbox (atau folder Spam) kamu.' : 'Gagal mengirim ulang email verifikasi. Coba lagi.',
        ),
        backgroundColor: success ? AppTheme.success : AppTheme.danger,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  Widget _verificationBadge(String status) {
    final labels = {'none': 'Belum Mengajukan', 'pending': 'Menunggu', 'approved': 'Terverifikasi', 'rejected': 'Ditolak'};
    final colors = {'none': AppTheme.muted, 'pending': AppTheme.warning, 'approved': AppTheme.success, 'rejected': AppTheme.danger};
    final color = colors[status] ?? AppTheme.muted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(labels[status] ?? status, style: TextStyle(color: color, fontSize: 10.5, fontWeight: FontWeight.w800)),
    );
  }

  Widget _card({required Widget child, EdgeInsetsGeometry? padding}) {
    return Container(
      width: double.infinity,
      padding: padding ?? const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
      ),
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.user;
    final firstLetter = (user?.name ?? 'P').trim().isNotEmpty ? user!.name.trim()[0].toUpperCase() : 'P';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Profil Pemilik'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Keluar',
            onPressed: () async {
              await authProvider.logout();
              if (context.mounted) context.go('/login');
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => authProvider.refreshUser(),
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (user != null && !user.isEmailVerified) ...[
                Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.warning.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppTheme.warning.withValues(alpha: 0.35)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.mark_email_unread_rounded, color: Color(0xFFD97706)),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Text(
                          'Email belum diverifikasi. Beberapa aksi (tambah kos, kelola booking) akan terkunci.',
                          style: TextStyle(fontSize: 12, color: Color(0xFF92400E), fontWeight: FontWeight.w700),
                        ),
                      ),
                      _isResending
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFD97706)))
                          : TextButton(
                              onPressed: () => _resendVerification(authProvider),
                              style: TextButton.styleFrom(foregroundColor: const Color(0xFFB45309)),
                              child: const Text('Kirim Ulang'),
                            ),
                    ],
                  ),
                ),
              ],
              _card(
                child: Column(
                  children: [
                    Stack(
                      clipBehavior: Clip.none,
                      children: [
                        UserAvatar(avatarUrl: user?.avatarUrl, name: firstLetter, size: 76),
                        if (_isUploadingAvatar)
                          const Positioned.fill(
                            child: CircleAvatar(
                              backgroundColor: Colors.black45,
                              child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                            ),
                          ),
                        Positioned(
                          right: -4,
                          bottom: -4,
                          child: Semantics(
                            button: true,
                            label: 'Ganti foto profil',
                            child: GestureDetector(
                              onTap: _isUploadingAvatar ? null : () => _changeAvatar(authProvider),
                              child: Container(
                                padding: const EdgeInsets.all(6),
                                decoration: BoxDecoration(
                                  color: AppTheme.primary,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: Theme.of(context).cardColor, width: 2),
                                ),
                                child: const Icon(Icons.camera_alt_rounded, size: 14, color: Colors.white),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Text(user?.name ?? 'Pemilik Kos', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 3),
                    Text(user?.email ?? '', style: Theme.of(context).textTheme.bodySmall),
                    if (user?.avatarUrl != null) ...[
                      const SizedBox(height: 6),
                      TextButton(
                        onPressed: _isUploadingAvatar ? null : () => _removeAvatar(authProvider),
                        child: const Text('Hapus Foto Profil', style: TextStyle(fontSize: 12.5)),
                      ),
                    ],
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                      decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                      child: const Text('Penyedia Kos', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w800, fontSize: 12)),
                    ),
                  ],
                ),
              ).animate().fadeIn(duration: 280.ms).slideY(begin: 0.06, end: 0),
              const SizedBox(height: 14),
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.verified_user_outlined, size: 18, color: AppTheme.primary),
                        const SizedBox(width: 8),
                        const Expanded(child: Text('Verifikasi Pemilik', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5))),
                        _verificationBadge(user?.ownerVerificationStatus ?? 'none'),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Kirim foto KTP atau dokumen kepemilikan supaya kos kamu dapat badge "Pemilik Terverifikasi".',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    if (user?.ownerVerificationStatus != 'approved') ...[
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: _isUploadingVerification ? null : () => _pickAndSubmitVerification(authProvider),
                          icon: _isUploadingVerification
                              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.upload_rounded, size: 18),
                          label: const Text('Kirim Dokumen'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.qr_code_rounded, size: 18, color: AppTheme.primary),
                        SizedBox(width: 8),
                        Text('Kode QRIS', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Ditampilkan otomatis ke penyewa begitu booking dikonfirmasi -- KosKita tetap tidak memproses pembayaran apa pun.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    if (user?.qrisUrl != null) ...[
                      const SizedBox(height: 12),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: CachedNetworkImage(imageUrl: user!.qrisUrl!, width: 140, height: 140, fit: BoxFit.contain),
                      ),
                      const SizedBox(height: 8),
                      TextButton(
                        onPressed: () => _deleteQris(authProvider),
                        style: TextButton.styleFrom(foregroundColor: AppTheme.danger),
                        child: const Text('Hapus QRIS'),
                      ),
                    ],
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: _isUploadingQris ? null : () => _pickAndUploadQris(authProvider),
                        icon: _isUploadingQris
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.upload_rounded, size: 18),
                        label: Text(user?.qrisUrl != null ? 'Ganti QRIS' : 'Unggah QRIS'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const ThemeModeSelector()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const BiometricToggle()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const TwoFactorToggle()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const NotificationPreferencesToggle()),
              const SizedBox(height: 14),
              _card(
                child: Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: () => openLegalPage(context, '/privacy'),
                        child: const Text('Kebijakan Privasi', style: TextStyle(fontSize: 12.5)),
                      ),
                    ),
                    Container(width: 1, height: 16, color: Theme.of(context).dividerTheme.color),
                    Expanded(
                      child: TextButton(
                        onPressed: () => openLegalPage(context, '/terms'),
                        child: const Text('Syarat & Ketentuan', style: TextStyle(fontSize: 12.5)),
                      ),
                    ),
                  ],
                ),
              ),
              const AppVersionLabel(),
            ],
          ),
        ),
      ),
    );
  }
}
