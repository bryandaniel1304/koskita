import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';
import '../utils/legal_links.dart';
import '../utils/image_source_picker.dart';
import '../widgets/theme_mode_selector.dart';
import '../widgets/biometric_toggle.dart';
import '../widgets/two_factor_toggle.dart';
import '../widgets/notification_preferences_toggle.dart';
import '../widgets/app_version_label.dart';
import '../widgets/user_avatar.dart';
import '../config/app_theme.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _isResending = false;
  bool _isUploadingAvatar = false;

  @override
  void initState() {
    super.initState();
    // Sinkronkan status verifikasi terbaru begitu layar Profil dibuka
    // (mis. setelah user balik dari klik link verifikasi di Gmail).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<AuthProvider>(context, listen: false).refreshUser();
    });
  }

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

  String _formatRupiah(int value) {
    return 'Rp ${(value / 1000000).toStringAsFixed(1)} jt';
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

  Widget _sectionLabel(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 12, top: 4),
        child: Text(text, style: Theme.of(context).textTheme.titleMedium),
      );

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final user = authProvider.user;
    final profile = user?.profile;
    final firstLetter = (user?.name ?? 'P').trim().isNotEmpty ? user!.name.trim()[0].toUpperCase() : 'P';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Profil Pengguna'),
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
                    Text(user?.name ?? 'Nama Pengguna', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 3),
                    Text(user?.email ?? 'email@example.com', style: Theme.of(context).textTheme.bodySmall),
                    if (user?.avatarUrl != null) ...[
                      const SizedBox(height: 6),
                      TextButton(
                        onPressed: _isUploadingAvatar ? null : () => _removeAvatar(authProvider),
                        child: const Text('Hapus Foto Profil', style: TextStyle(fontSize: 12.5)),
                      ),
                    ],
                  ],
                ),
              ).animate().fadeIn(duration: 280.ms).slideY(begin: 0.06, end: 0),
              if (user != null && !user.isEmailVerified) ...[
                const SizedBox(height: 14),
                Container(
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
                        child: Text('Email kamu belum diverifikasi.', style: TextStyle(fontSize: 12.5, color: Color(0xFF92400E), fontWeight: FontWeight.w700)),
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

              _sectionLabel('Preferensi Kos Kamu'),
              _card(
                child: profile == null
                    ? const Center(child: Text('Profil belum dikonfigurasi.'))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildProfileRow(context, 'Jenis Kelamin', profile.gender.toUpperCase()),
                          const Divider(height: 26),
                          _buildProfileRow(context, 'Pekerjaan', profile.occupation.toUpperCase()),
                          const Divider(height: 26),
                          _buildProfileRow(context, 'Area Kampus', profile.preferredLocation),
                          const Divider(height: 26),
                          _buildProfileRow(context, 'Rentang Anggaran', '${_formatRupiah(profile.budgetMin)} - ${_formatRupiah(profile.budgetMax)}'),
                          const Divider(height: 26),
                          Text('Fasilitas yang Diinginkan', style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          profile.preferredFacilities.isEmpty
                              ? Text('Tidak ada fasilitas spesifik', style: Theme.of(context).textTheme.bodySmall)
                              : Wrap(spacing: 6, runSpacing: 6, children: profile.preferredFacilities.map((f) => Chip(label: Text(f))).toList()),
                          const Divider(height: 26),
                          Text('Aturan yang Ditoleransi', style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          profile.preferredRules.isEmpty
                              ? Text('Tidak ada aturan spesifik', style: Theme.of(context).textTheme.bodySmall)
                              : Wrap(spacing: 6, runSpacing: 6, children: profile.preferredRules.map((r) => Chip(label: Text(r))).toList()),
                        ],
                      ),
              ),

              _sectionLabel('Pengaturan Akun'),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const ThemeModeSelector()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const BiometricToggle()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const TwoFactorToggle()),
              const SizedBox(height: 14),
              _card(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4), child: const NotificationPreferencesToggle()),
              const SizedBox(height: 14),
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    OutlinedButton.icon(
                      icon: const Icon(Icons.edit_rounded, size: 18),
                      label: const Text('Edit Profil Preferensi'),
                      onPressed: () => context.push('/onboarding'),
                      style: OutlinedButton.styleFrom(foregroundColor: AppTheme.primary, side: const BorderSide(color: AppTheme.primaryLight, width: 1.4)),
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton.icon(
                      icon: const Icon(Icons.refresh_rounded, size: 18),
                      label: const Text('Reset Riwayat Rating & Favorit'),
                      onPressed: () async {
                        final success = await authProvider.resetInteractions();
                        if (success && context.mounted) {
                          await kosProvider.fetchRecommendations();
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Riwayat rating & favorit direset. Rekomendasi akan dihitung ulang dari awal.'), backgroundColor: AppTheme.primaryLight),
                          );
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Theme.of(context).inputDecorationTheme.fillColor,
                        foregroundColor: AppTheme.primary,
                        elevation: 0,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
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

  Widget _buildProfileRow(BuildContext context, String title, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.muted, fontSize: 13)),
        const SizedBox(width: 12),
        // Flexible -- tanpa ini, value yang panjang (mis. rentang anggaran)
        // bisa overflow di HP sempit atau saat pengguna set skala font
        // sistem besar (Pengaturan Aksesibilitas), bukan cuma kepotong rapi.
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            // Warna value SENGAJA diambil dari tema (bukan AppTheme.ink yang
            // hardcode hampir-hitam) -- kalau tidak, teksnya jadi tak kebaca
            // di mode gelap karena nyaris menyatu dengan latar kartu yang gelap.
            style: TextStyle(fontWeight: FontWeight.w800, color: Theme.of(context).textTheme.bodyLarge?.color, fontSize: 13.5),
          ),
        ),
      ],
    );
  }
}
