import 'package:flutter/material.dart';
import '../services/biometric_auth_service.dart';
import '../config/app_theme.dart';

/// Baris pengaturan "Buka dengan Sidik Jari/Wajah" -- dipakai di layar
/// Profil penyewa maupun pemilik. Cuma tampil kalau perangkatnya memang
/// punya sensor yang sudah didaftarkan (tidak menampilkan toggle percuma
/// di HP yang tidak mendukung).
class BiometricToggle extends StatefulWidget {
  const BiometricToggle({super.key});

  @override
  State<BiometricToggle> createState() => _BiometricToggleState();
}

class _BiometricToggleState extends State<BiometricToggle> {
  bool _supported = false;
  bool _enabled = false;
  bool _checked = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final supported = await BiometricAuthService.isDeviceSupported();
    final enabled = await BiometricAuthService.isEnabled();
    if (!mounted) return;
    setState(() {
      _supported = supported;
      _enabled = enabled;
      _checked = true;
    });
  }

  Future<void> _toggle(bool value) async {
    setState(() => _busy = true);
    if (value) {
      // Minta konfirmasi biometrik dulu SEBELUM diaktifkan -- memastikan
      // sensor benar-benar berfungsi untuk pemilik akun ini, bukan cuma
      // menyalakan toggle lalu baru ketahuan gagal saat buka app berikutnya.
      final confirmed = await BiometricAuthService.authenticate(
        reason: 'Konfirmasi sidik jari/wajah untuk mengaktifkan buka cepat',
      );
      if (!confirmed) {
        if (mounted) {
          setState(() => _busy = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Gagal memverifikasi. Coba lagi.')),
          );
        }
        return;
      }
    }
    await BiometricAuthService.setEnabled(value);
    if (!mounted) return;
    setState(() {
      _enabled = value;
      _busy = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_checked || !_supported) return const SizedBox.shrink();

    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      secondary: const Icon(Icons.fingerprint_rounded, color: AppTheme.primary),
      title: const Text('Buka dengan Sidik Jari/Wajah', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
      subtitle: const Text('Lebih cepat dari ketik ulang kata sandi tiap buka aplikasi', style: TextStyle(fontSize: 11.5)),
      value: _enabled,
      onChanged: _busy ? null : _toggle,
    );
  }
}
