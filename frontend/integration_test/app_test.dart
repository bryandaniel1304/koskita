import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/main.dart';
import 'package:frontend/providers/auth_provider.dart';
import 'package:frontend/router/app_router.dart';

/// Test end-to-end SUNGGUHAN (bukan widget test terisolasi seperti
/// test/screens/login_register_screen_test.dart) -- ini boot app LEWAT
/// jalur yang sama persis dengan main.dart (buildRouter + AuthProvider
/// nyata, bukan router/provider tiruan khusus test), lalu navigasi
/// beneran lewat splash -> walkthrough -> login, isi form, dst.
///
/// WAJIB dijalankan di device/emulator sungguhan (`flutter test
/// integration_test/app_test.dart -d DEVICE_ID` atau `flutter drive`)
/// -- BUKAN via `flutter test` biasa. App ini pakai banyak plugin native
/// (flutter_secure_storage, local_auth, dst) yang cuma benar-benar ada
/// kalau jalan di atas platform sungguhan, tidak di lingkungan headless.
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  group('Alur masuk KosKita', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({'has_seen_walkthrough': true});
    });

    testWidgets('splash -> login -> validasi form registrasi', (tester) async {
      final authProvider = AuthProvider();
      final router = buildRouter(authProvider);

      await tester.pumpWidget(
        MultiProvider(
          providers: [ChangeNotifierProvider.value(value: authProvider)],
          child: MyApp(router: router),
        ),
      );

      // Splash nunggu ~1.8 detik sebelum cek auto-login & pindah layar.
      await tester.pump(const Duration(milliseconds: 1900));
      await tester.pumpAndSettle();

      // Tidak ada token tersimpan -> harus mendarat di layar Masuk.
      expect(find.text('Masuk'), findsWidgets);

      // Pindah ke mode Daftar.
      await tester.tap(find.text('Belum punya akun? Daftar sekarang'));
      await tester.pumpAndSettle();

      // Submit tanpa pilih peran -- harus muncul validasi, BUKAN lanjut
      // ke layar berikutnya (memverifikasi validasi client-side beneran
      // jalan lewat alur app yang sesungguhnya, bukan cuma widget terisolasi).
      await tester.tap(find.text('Daftar').last);
      await tester.pump();

      expect(find.text('Pilih dulu kamu mendaftar sebagai apa.'), findsOneWidget);
    });
  });
}
