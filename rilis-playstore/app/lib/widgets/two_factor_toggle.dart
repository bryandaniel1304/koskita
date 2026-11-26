import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../config/app_theme.dart';

/// Baris pengaturan "Verifikasi 2 Langkah" -- dipakai di layar Profil
/// penyewa maupun pemilik. Nonaktifkan langsung sekali tap (sudah login,
/// tidak perlu bukti tambahan); aktifkan butuh konfirmasi kode dari email
/// dulu (lihat AuthProvider.startEnableTwoFactor/confirmEnableTwoFactor)
/// supaya tidak menyandarkan login berikutnya ke email yang salah ketik.
class TwoFactorToggle extends StatefulWidget {
  const TwoFactorToggle({super.key});

  @override
  State<TwoFactorToggle> createState() => _TwoFactorToggleState();
}

class _TwoFactorToggleState extends State<TwoFactorToggle> {
  final _codeController = TextEditingController();
  bool _busy = false;
  bool _awaitingCode = false;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _toggle(bool value, AuthProvider authProvider) async {
    setState(() => _busy = true);

    if (!value) {
      final ok = await authProvider.disableTwoFactor();
      if (!mounted) return;
      setState(() => _busy = false);
      if (!ok) _showMessage('Gagal menonaktifkan. Coba lagi.');
      return;
    }

    final sent = await authProvider.startEnableTwoFactor();
    if (!mounted) return;
    setState(() {
      _busy = false;
      _awaitingCode = sent;
    });
    if (sent) {
      _showMessage('Kode konfirmasi sudah dikirim ke emailmu.');
    } else {
      _showMessage('Gagal mengirim kode konfirmasi. Coba lagi.');
    }
  }

  Future<void> _confirm(AuthProvider authProvider) async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    setState(() => _busy = true);
    final ok = await authProvider.confirmEnableTwoFactor(code);
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (ok) _awaitingCode = false;
    });
    _codeController.clear();
    _showMessage(ok ? 'Verifikasi 2 langkah aktif.' : (authProvider.errorMessage ?? 'Kode salah atau sudah kedaluwarsa.'));
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final enabled = authProvider.user?.twoFactorEnabled ?? false;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          secondary: const Icon(Icons.shield_moon_rounded, color: AppTheme.primary),
          title: const Text('Verifikasi 2 Langkah', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
          subtitle: const Text('Kode dikirim ke email tiap masuk, lapisan keamanan tambahan', style: TextStyle(fontSize: 11.5)),
          value: enabled,
          onChanged: _busy ? null : (value) => _toggle(value, authProvider),
        ),
        if (_awaitingCode) ...[
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _codeController,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: const InputDecoration(hintText: 'Kode dari email', counterText: '', isDense: true),
                ),
              ),
              const SizedBox(width: 8),
              ElevatedButton(
                onPressed: _busy ? null : () => _confirm(authProvider),
                child: const Text('Konfirmasi'),
              ),
            ],
          ),
          const SizedBox(height: 8),
        ],
      ],
    );
  }
}
