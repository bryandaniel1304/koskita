import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:frontend/providers/auth_provider.dart';
import 'package:frontend/utils/logout_confirmation.dart';

class _FakeAuthProvider extends AuthProvider {
  int logoutCalls = 0;

  @override
  Future<void> logout() async {
    logoutCalls++;
  }
}

void main() {
  late _FakeAuthProvider auth;

  Widget app() {
    final router = GoRouter(routes: [
      GoRoute(
        path: '/',
        builder: (context, state) => Scaffold(
          body: Builder(
            builder: (context) => TextButton(
              onPressed: () => confirmAndLogout(context),
              child: const Text('Keluar'),
            ),
          ),
        ),
      ),
      GoRoute(path: '/login', builder: (context, state) => const Scaffold(body: Text('Halaman Masuk'))),
    ]);

    return ChangeNotifierProvider<AuthProvider>.value(
      value: auth,
      child: MaterialApp.router(routerConfig: router),
    );
  }

  setUp(() => auth = _FakeAuthProvider());

  testWidgets('tapping Keluar asks for confirmation first (UC-08)', (tester) async {
    await tester.pumpWidget(app());
    await tester.tap(find.text('Keluar'));
    await tester.pumpAndSettle();

    expect(find.text('Keluar dari Akun'), findsOneWidget);
    expect(auth.logoutCalls, 0);
  });

  testWidgets('cancelling keeps the session', (tester) async {
    await tester.pumpWidget(app());
    await tester.tap(find.text('Keluar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();

    expect(find.text('Keluar dari Akun'), findsNothing);
    expect(find.text('Halaman Masuk'), findsNothing);
    expect(auth.logoutCalls, 0);
  });

  testWidgets('confirming logs out and returns to the login page', (tester) async {
    await tester.pumpWidget(app());
    await tester.tap(find.text('Keluar'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(TextButton, 'Keluar').last);
    await tester.pumpAndSettle();

    expect(auth.logoutCalls, 1);
    expect(find.text('Halaman Masuk'), findsOneWidget);
  });
}
