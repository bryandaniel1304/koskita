import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../config/app_theme.dart';

/// Tanya dulu "Ambil Foto" atau "Pilih dari Galeri" sebelum buka
/// image_picker -- dipakai di form yang butuh foto dokumen (verifikasi
/// pemilik, QRIS) supaya bisa langsung difoto di tempat tanpa keluar
/// aplikasi buka kamera lalu balik pilih dari galeri. Return null kalau
/// dialog ditutup tanpa memilih.
Future<XFile?> pickImageWithSourceChoice(BuildContext context, {String title = 'Pilih Foto'}) async {
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    backgroundColor: Colors.transparent,
    builder: (sheetContext) => Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 40,
            height: 4,
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(4)),
          ),
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          ListTile(
            leading: const Icon(Icons.camera_alt_rounded, color: AppTheme.primary),
            title: const Text('Ambil Foto Sekarang'),
            onTap: () => Navigator.pop(sheetContext, ImageSource.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_rounded, color: AppTheme.primary),
            title: const Text('Pilih dari Galeri'),
            onTap: () => Navigator.pop(sheetContext, ImageSource.gallery),
          ),
        ],
      ),
    ),
  );

  if (source == null || !context.mounted) return null;
  return ImagePicker().pickImage(source: source, imageQuality: 85);
}
