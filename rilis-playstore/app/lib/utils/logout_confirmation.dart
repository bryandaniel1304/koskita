import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

/// Tombol Keluar di profil penyewa maupun pemilik: minta konfirmasi dulu
/// (UC-08) supaya sesi tidak berakhir karena salah ketuk ikon di app bar.
Future<void> confirmAndLogout(BuildContext context) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Keluar dari Akun'),
      content: const Text('Kamu perlu masuk lagi untuk memakai KosKita.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
        TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Keluar')),
      ],
    ),
  );
  if (confirmed != true || !context.mounted) return;

  await Provider.of<AuthProvider>(context, listen: false).logout();
  if (context.mounted) context.go('/login');
}
