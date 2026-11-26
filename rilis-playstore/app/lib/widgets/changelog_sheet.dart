import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../services/app_version_service.dart';

/// Dialog ringkas "Yang Baru" -- tampil SEKALI begitu pengguna buka app
/// setelah update ke versi baru (bukan tiap kali buka app). Lihat
/// AppVersionService.checkForChangelog untuk logika deteksinya.
class ChangelogSheet {
  static Future<void> maybeShow(BuildContext context) async {
    final notes = await AppVersionService.checkForChangelog();
    if (notes == null || notes.isEmpty || !context.mounted) return;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Container(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
          decoration: BoxDecoration(
            color: Theme.of(sheetContext).scaffoldBackgroundColor,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
                    alignment: Alignment.center,
                    child: const Icon(Icons.auto_awesome_rounded, color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text('Ada yang Baru di KosKita', style: Theme.of(sheetContext).textTheme.titleLarge),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              ...notes.map(
                (note) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.check_circle_rounded, color: AppTheme.success, size: 18),
                      const SizedBox(width: 10),
                      Expanded(child: Text(note, style: Theme.of(sheetContext).textTheme.bodyMedium)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(sheetContext),
                  child: const Text('Oke, Mengerti'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
