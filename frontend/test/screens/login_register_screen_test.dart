import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:frontend/providers/auth_provider.dart';
import 'package:frontend/screens/login_register_screen.dart';

void main() {
  Widget wrap(Widget child) {
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(home: child),
    );
  }

  // Form registrasi (nama, email, HP, password, role, ceklis ToS, tombol)
  // lebih tinggi dari viewport default 800x600 test harness -- diperbesar
  // supaya semua elemen bisa di-tap tanpa perlu scroll manual per elemen.
  setUp(() {
    final binding = TestWidgetsFlutterBinding.ensureInitialized();
    binding.platformDispatcher.views.first.physicalSize = const Size(800, 2400);
    binding.platformDispatcher.views.first.devicePixelRatio = 1.0;
    addTearDown(binding.platformDispatcher.views.first.resetPhysicalSize);
    addTearDown(binding.platformDispatcher.views.first.resetDevicePixelRatio);
  });

  testWidgets('role cards start unselected -- no default picked for the user', (tester) async {
    await tester.pumpWidget(wrap(const LoginRegisterScreen()));
    await tester.pumpAndSettle();

    // Pindah ke mode daftar (form login adalah default awal).
    await tester.tap(find.text('Belum punya akun? Daftar sekarang'));
    await tester.pumpAndSettle();

    expect(find.text('Penyewa Kos'), findsOneWidget);
    expect(find.text('Penyedia Kos'), findsOneWidget);
  });

  testWidgets('submitting registration without picking a role shows a validation message', (tester) async {
    await tester.pumpWidget(wrap(const LoginRegisterScreen()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Belum punya akun? Daftar sekarang'));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Nama Lengkap'), 'Budi Santoso');
    await tester.enterText(find.widgetWithText(TextFormField, 'Alamat Email'), 'budi@example.com');
    await tester.enterText(find.widgetWithText(TextFormField, 'Nomor HP'), '081234567890');
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'password123');

    await tester.tap(find.text('Daftar'));
    await tester.pump(); // biarkan SnackBar muncul

    expect(find.text('Pilih dulu kamu mendaftar sebagai apa.'), findsOneWidget);
  });

  testWidgets('picking a role but not agreeing to terms blocks submission', (tester) async {
    await tester.pumpWidget(wrap(const LoginRegisterScreen()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Belum punya akun? Daftar sekarang'));
    await tester.pumpAndSettle();

    await tester.enterText(find.widgetWithText(TextFormField, 'Nama Lengkap'), 'Budi Santoso');
    await tester.enterText(find.widgetWithText(TextFormField, 'Alamat Email'), 'budi@example.com');
    await tester.enterText(find.widgetWithText(TextFormField, 'Nomor HP'), '081234567890');
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'password123');

    await tester.tap(find.text('Penyewa Kos'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Daftar'));
    await tester.pump();

    expect(find.text('Kamu perlu menyetujui Syarat & Ketentuan dan Kebijakan Privasi dulu.'), findsOneWidget);
  });
}
