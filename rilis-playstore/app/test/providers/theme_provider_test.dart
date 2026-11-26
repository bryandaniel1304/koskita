import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:frontend/providers/theme_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test('defaults to ThemeMode.system when nothing is saved', () async {
    final provider = ThemeProvider();
    // Konstruktor memuat preferensi tersimpan secara async -- tunggu event
    // loop selesai dulu sebelum diperiksa.
    await Future<void>.delayed(Duration.zero);

    expect(provider.themeMode, ThemeMode.system);
  });

  test('setThemeMode persists and updates the mode', () async {
    final provider = ThemeProvider();
    await Future<void>.delayed(Duration.zero);

    await provider.setThemeMode(ThemeMode.dark);
    expect(provider.themeMode, ThemeMode.dark);

    // Provider baru (simulasi restart app) harus baca preferensi yang barusan disimpan.
    final reloaded = ThemeProvider();
    await Future<void>.delayed(Duration.zero);
    expect(reloaded.themeMode, ThemeMode.dark);
  });
}
