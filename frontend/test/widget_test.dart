import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:frontend/main.dart';

void main() {
  testWidgets('App boots to splash screen', (WidgetTester tester) async {
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (context, state) => const Scaffold(body: Text('KOSKITA'))),
    ]);

    await tester.pumpWidget(MyApp(router: router));

    expect(find.text('KOSKITA'), findsOneWidget);
  });
}
