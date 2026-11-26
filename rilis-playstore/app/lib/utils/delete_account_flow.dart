import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

/// Tombol "Hapus Akun" di profil penyewa & pemilik -- WAJIB menurut
/// kebijakan Google Play untuk aplikasi yang mengizinkan pembuatan akun.
/// Pengguna harus mengetik HAPUS supaya akun tidak terhapus karena salah
/// ketuk (penghapusan permanen, tidak bisa dibatalkan).
Future<void> confirmAndDeleteAccount(BuildContext context) async {
  final isOwner = Provider.of<AuthProvider>(context, listen: false).user?.role == 'owner';
  final controller = TextEditingController();

  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: const Text('Hapus Akun Permanen'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                isOwner
                    ? 'Akun, seluruh kos yang kamu daftarkan beserta fotonya, booking, ulasan, dan pesan akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.'
                    : 'Akun, profil preferensi, riwayat favorit/rating, booking, ulasan, dan pesan akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.',
              ),
              const SizedBox(height: 16),
              const Text('Ketik HAPUS untuk melanjutkan:'),
              const SizedBox(height: 8),
              TextField(
                controller: controller,
                autofocus: true,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(hintText: 'HAPUS'),
                onChanged: (_) => setState(() {}),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          TextButton(
            onPressed: controller.text.trim() == 'HAPUS' ? () => Navigator.pop(context, true) : null,
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Hapus Permanen'),
          ),
        ],
      ),
    ),
  );
  controller.dispose();
  if (confirmed != true || !context.mounted) return;

  final error = await Provider.of<AuthProvider>(context, listen: false).deleteAccount();
  if (!context.mounted) return;
  if (error != null) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    return;
  }
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(content: Text('Akun kamu sudah dihapus.')),
  );
  context.go('/login');
}

/// Tombol teks merah yang dipasang di bagian bawah kedua layar profil.
class DeleteAccountButton extends StatelessWidget {
  const DeleteAccountButton({super.key});

  @override
  Widget build(BuildContext context) {
    return TextButton.icon(
      onPressed: () => confirmAndDeleteAccount(context),
      icon: const Icon(Icons.delete_forever_rounded, size: 18),
      label: const Text('Hapus Akun', style: TextStyle(fontSize: 12.5)),
      style: TextButton.styleFrom(foregroundColor: Colors.red.shade400),
    );
  }
}
