import 'package:flutter/material.dart';
import '../services/app_version_service.dart';

/// Nomor versi app kecil di bawah halaman Profil -- kecil tapi berguna
/// buat pengguna sertakan di laporan bug, dan jadi sinyal kecil app yang
/// dirawat serius (dibanding tidak ada info versi sama sekali).
class AppVersionLabel extends StatelessWidget {
  const AppVersionLabel({super.key});

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String>(
      future: AppVersionService.versionLabel(),
      builder: (context, snapshot) {
        if (!snapshot.hasData) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.only(top: 16, bottom: 4),
          child: Text(
            'KosKita ${snapshot.data}',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 11),
          ),
        );
      },
    );
  }
}
