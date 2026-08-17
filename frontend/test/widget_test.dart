import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'package:frontend/main.dart';
import 'package:frontend/providers/theme_provider.dart';

void main() {
  testWidgets('App boots and renders its router content', (WidgetTester tester) async {
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (context, state) => const Scaffold(body: Text('KOSKITA'))),
    ]);

    // MyApp membaca ThemeProvider lewat context.watch, jadi widget test-nya
    // wajib disediakan provider yang sama seperti di main() -- tanpa ini
    // akan lempar ProviderNotFoundException.
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => ThemeProvider(),
        child: MyApp(router: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('KOSKITA'), findsOneWidget);
  });
}
