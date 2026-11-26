import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../config/app_theme.dart';
import '../widgets/premium_button.dart';

/// Layar kode verifikasi 2 langkah -- dituju dari login_register_screen
/// begitu AuthProvider.login() balas LoginResult.requiresTwoFactor. Kode
/// dikirim ke email pengguna (lihat backend AuthController::login).
class TwoFactorScreen extends StatefulWidget {
  const TwoFactorScreen({super.key});

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _codeController = TextEditingController();
  bool _isVerifying = false;
  bool _isResending = false;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    setState(() => _isVerifying = true);
    final success = await authProvider.verifyTwoFactorCode(code);
    if (!mounted) return;
    setState(() => _isVerifying = false);

    if (success) {
      final isOwner = authProvider.user?.role == 'owner';
      context.go(isOwner ? '/owner/koses' : '/home');
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(authProvider.errorMessage ?? 'Kode salah atau sudah kedaluwarsa.'),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  Future<void> _resend() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    setState(() => _isResending = true);
    final success = await authProvider.resendTwoFactorCode();
    if (!mounted) return;
    setState(() => _isResending = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Kode baru sudah dikirim ke emailmu.' : 'Gagal mengirim ulang kode. Coba lagi.')),
    );
  }

  void _cancel() {
    Provider.of<AuthProvider>(context, listen: false).cancelTwoFactorChallenge();
    context.pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
                alignment: Alignment.center,
                child: const Icon(Icons.shield_moon_rounded, color: Colors.white, size: 32),
              ),
              const SizedBox(height: 24),
              Text('Verifikasi 2 Langkah', style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text(
                'Kami kirim kode 6 digit ke emailmu. Masukkan di bawah ini buat lanjut masuk.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: 28),
              TextField(
                controller: _codeController,
                keyboardType: TextInputType.number,
                textAlign: TextAlign.center,
                maxLength: 6,
                autofocus: true,
                style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: 8),
                decoration: const InputDecoration(counterText: '', hintText: '------'),
                onSubmitted: (_) => _verify(),
              ),
              const SizedBox(height: 10),
              PremiumButton(label: 'Verifikasi', onPressed: _verify, loading: _isVerifying),
              const SizedBox(height: 8),
              TextButton(
                onPressed: _isResending ? null : _resend,
                child: Text(_isResending ? 'Mengirim...' : 'Kirim Ulang Kode'),
              ),
              TextButton(
                onPressed: _cancel,
                child: const Text('Bukan kamu? Kembali ke halaman masuk'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
