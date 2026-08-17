import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../providers/auth_provider.dart';
import '../config/app_theme.dart';
import '../widgets/gradient_blob_background.dart';
import '../services/biometric_auth_service.dart';
import '../services/deep_link_service.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;
  late Animation<double> _scaleAnimation;
  late Animation<double> _taglineFade;

  /// true kalau auto-login berhasil TAPI pengguna sudah aktifkan buka
  /// cepat lewat sidik jari/wajah -- tahan dulu di sini sampai
  /// terverifikasi, jangan langsung lempar ke beranda.
  bool _awaitingBiometric = false;
  bool _biometricFailed = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    );
    _fadeAnimation = CurvedAnimation(parent: _controller, curve: const Interval(0.0, 0.6, curve: Curves.easeOut));
    _scaleAnimation = Tween<double>(begin: 0.82, end: 1.0).animate(
      CurvedAnimation(parent: _controller, curve: const Interval(0.0, 0.7, curve: Curves.easeOutBack)),
    );
    _taglineFade = CurvedAnimation(parent: _controller, curve: const Interval(0.45, 1.0, curve: Curves.easeOut));
    _controller.forward();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    // Tunggu sedikit agar animasi splash terlihat
    await Future.delayed(const Duration(milliseconds: 1800));
    await authProvider.tryAutoLogin();

    if (!mounted) return;
    if (!authProvider.isAuthenticated) {
      final prefs = await SharedPreferences.getInstance();
      final hasSeenWalkthrough = prefs.getBool('has_seen_walkthrough') ?? false;
      if (!mounted) return;
      context.go(hasSeenWalkthrough ? '/login' : '/walkthrough');
      return;
    }

    final biometricEnabled = await BiometricAuthService.isEnabled();
    if (!mounted) return;
    if (biometricEnabled) {
      setState(() => _awaitingBiometric = true);
      _attemptBiometric();
      return;
    }

    context.go(await _destinationAfterLogin(authProvider));
  }

  Future<void> _attemptBiometric() async {
    setState(() => _biometricFailed = false);
    final success = await BiometricAuthService.authenticate();
    if (!mounted) return;
    if (success) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      context.go(await _destinationAfterLogin(authProvider));
    } else {
      setState(() => _biometricFailed = true);
    }
  }

  /// Rute yang dituju sesudah sesi terverifikasi -- kalau app dibuka lewat
  /// deep link ("koskita://kos/12" atau App Link produksi nanti), langsung
  /// ke situ; kalau tidak, ke beranda sesuai peran seperti biasa.
  Future<String> _destinationAfterLogin(AuthProvider authProvider) async {
    final deepLinkPath = await DeepLinkService.consumePendingInitialPath();
    if (deepLinkPath != null) return deepLinkPath;
    return authProvider.user?.role == 'owner' ? '/owner/koses' : '/home';
  }

  Future<void> _logoutInstead() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    await authProvider.logout();
    if (!mounted) return;
    context.go('/login');
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: GradientBlobBackground(
        child: Center(
          child: FadeTransition(
            opacity: _fadeAnimation,
            child: ScaleTransition(
              scale: _scaleAnimation,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(34),
                      boxShadow: AppTheme.softShadow(tint: AppTheme.primary, opacity: 0.18),
                    ),
                    child: Image.asset('assets/images/logo.png', width: 240),
                  ),
                  const SizedBox(height: 18),
                  FadeTransition(
                    opacity: _taglineFade,
                    child: const Text(
                      'Cari kos yang paling cocok, bukan cuma yang ada.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: AppTheme.muted),
                    ),
                  ),
                  const SizedBox(height: 44),
                  if (_awaitingBiometric) ...[
                    Icon(
                      Icons.fingerprint_rounded,
                      size: 48,
                      color: _biometricFailed ? AppTheme.danger : AppTheme.primary,
                    ),
                    const SizedBox(height: 10),
                    Text(
                      _biometricFailed ? 'Verifikasi gagal/dibatalkan' : 'Menunggu verifikasi...',
                      style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: _biometricFailed ? AppTheme.danger : AppTheme.muted),
                    ),
                    if (_biometricFailed) ...[
                      const SizedBox(height: 14),
                      ElevatedButton.icon(
                        onPressed: _attemptBiometric,
                        icon: const Icon(Icons.refresh_rounded, size: 18),
                        label: const Text('Coba Lagi'),
                      ),
                      const SizedBox(height: 8),
                      TextButton(
                        onPressed: _logoutInstead,
                        child: const Text('Keluar & Masuk dengan Kata Sandi'),
                      ),
                    ],
                  ] else
                  const SizedBox(
                    width: 26,
                    height: 26,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.6,
                      valueColor: AlwaysStoppedAnimation<Color>(AppTheme.primary),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
