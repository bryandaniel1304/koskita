import 'package:flutter/material.dart';
import 'haptics.dart';

/// Snackbar dengan tombol "Batalkan" -- pola modern pengganti dialog
/// konfirmasi berlapis untuk aksi yang gampang dibalik (unfavorite, hapus
/// filter tersimpan). Aksinya sendiri SUDAH dieksekusi duluan sebelum
/// snackbar ini tampil (bukan ditunda) -- [onUndo] cukup memanggil ulang
/// aksi kebalikannya kalau pengguna sempat tekan "Batalkan".
void showUndoSnackBar(
  BuildContext context, {
  required String message,
  required VoidCallback onUndo,
  Duration duration = const Duration(seconds: 4),
}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message),
        duration: duration,
        action: SnackBarAction(
          label: 'Batalkan',
          onPressed: () {
            Haptics.selection();
            onUndo();
          },
        ),
      ),
    );
}
