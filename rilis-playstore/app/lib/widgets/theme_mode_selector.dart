import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/theme_provider.dart';

/// Selektor mode tema (Terang/Gelap/Sistem) -- dipakai di layar Profil
/// penyewa maupun pemilik kos.
class ThemeModeSelector extends StatelessWidget {
  const ThemeModeSelector({super.key});

  @override
  Widget build(BuildContext context) {
    final themeProvider = Provider.of<ThemeProvider>(context);

    return Row(
      children: [
        const Icon(Icons.dark_mode_outlined, size: 18, color: Color(0xFF64748B)),
        const SizedBox(width: 8),
        const Expanded(
          child: Text('Tema Aplikasi', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        ),
        DropdownButton<ThemeMode>(
          value: themeProvider.themeMode,
          underline: const SizedBox(),
          items: const [
            DropdownMenuItem(value: ThemeMode.system, child: Text('Ikuti Sistem', style: TextStyle(fontSize: 13))),
            DropdownMenuItem(value: ThemeMode.light, child: Text('Terang', style: TextStyle(fontSize: 13))),
            DropdownMenuItem(value: ThemeMode.dark, child: Text('Gelap', style: TextStyle(fontSize: 13))),
          ],
          onChanged: (mode) {
            if (mode != null) themeProvider.setThemeMode(mode);
          },
        ),
      ],
    );
  }
}
