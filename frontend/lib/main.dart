import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'providers/auth_provider.dart';
import 'providers/kos_provider.dart';
import 'providers/booking_provider.dart';
import 'providers/chatbot_provider.dart';
import 'providers/owner_kos_provider.dart';
import 'providers/theme_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/message_provider.dart';
import 'config/app_config.dart';
import 'config/app_theme.dart';
import 'router/app_router.dart';
import 'services/notification_scheduler.dart';
import 'services/app_shortcuts_service.dart';
import 'services/deep_link_service.dart';
import 'services/fcm_service.dart';

/// Diisi lewat `flutter run/build --dart-define=SENTRY_DSN=...` -- KOSONG
/// SENGAJA (tidak ada nilai default dummy) sampai admin bikin project
/// Sentry sendiri (gratis, lihat catatan setup di backend/.env). Kosong =
/// SentryFlutter.init() dilewati sama sekali (bukan dipanggil dengan DSN
/// kosong), app berjalan persis seperti sebelum Sentry dipasang.
const String _sentryDsn = String.fromEnvironment('SENTRY_DSN');

void main() async {
  if (_sentryDsn.isEmpty) {
    await _bootstrap();
    return;
  }

  await SentryFlutter.init(
    (options) {
      options.dsn = _sentryDsn;
      // 20% dari trace performa -- cukup buat lihat pola tanpa membanjiri
      // kuota gratis Sentry dengan setiap satu request.
      options.tracesSampleRate = 0.2;
    },
    appRunner: _bootstrap,
  );
}

Future<void> _bootstrap() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID');
  // Harus selesai sebelum widget pertama sempat memanggil API (splash
  // langsung coba auto-login) -- kalau tidak, request pertama masih pakai
  // alamat default sebelum override tersimpan sempat terbaca.
  await AppConfig.loadRuntimeOverride();
  // Pengingat sewa lokal -- lihat NotificationScheduler untuk kenapa ini
  // tidak butuh akun Firebase sama sekali.
  await NotificationScheduler.initialize();
  // Push notification asli -- lihat FcmService untuk kenapa ini AMAN
  // dipanggil walau project Firebase belum dikonfigurasi sama sekali
  // (gagal diam-diam, tidak melempar ke sini).
  await FcmService.initialize();

  final authProvider = AuthProvider();
  final router = buildRouter(authProvider);
  // Shortcut tekan-lama ikon aplikasi -- daftar itemnya disinkronkan sesuai
  // peran tiap kali status login berubah, lihat AuthProvider.
  await AppShortcutsService.initialize(router);
  unawaited(AppShortcutsService.syncShortcuts(authProvider.user?.role));
  // Deep link "koskita://kos/{id}" (dan App Link https produksi nanti) --
  // tautan cold-start ditangani terpisah di SplashScreen sesudah auto-login.
  DeepLinkService.initialize(router);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider(create: (_) => KosProvider()),
        ChangeNotifierProvider(create: (_) => BookingProvider()),
        ChangeNotifierProvider(create: (_) => ChatbotProvider()),
        ChangeNotifierProvider(create: (_) => OwnerKosProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
        ChangeNotifierProvider(create: (_) => MessageProvider()),
      ],
      child: MyApp(router: router),
    ),
  );
}

class MyApp extends StatefulWidget {
  final RouterConfig<Object> router;

  const MyApp({super.key, required this.router});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Push (FCM) yang masuk SELAGI app terbuka tidak otomatis tampil
    // sebagai notifikasi sistem (perilaku bawaan Android/iOS) -- di sini
    // cukup refresh badge yang relevan supaya tetap terasa "real-time"
    // tanpa perlu bikin notifikasi lokal duplikat manual.
    FcmService.onForegroundMessage = (data) {
      if (!mounted) return;
      switch (data['type']) {
        case 'message':
          Provider.of<MessageProvider>(context, listen: false).fetchUnreadCount();
          break;
        case 'booking':
        case 'waitlist':
          Provider.of<NotificationProvider>(context, listen: false).fetchNotifications();
          break;
      }
    };
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Saat app kembali aktif (mis. user habis buka link verifikasi email di
    // Gmail lalu balik ke app), sinkronkan ulang status user -- ini yang
    // bikin banner "email belum diverifikasi" otomatis hilang tanpa perlu
    // logout/login manual.
    if (state == AppLifecycleState.resumed) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      if (authProvider.isAuthenticated) {
        authProvider.refreshUser();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final themeProvider = Provider.of<ThemeProvider>(context);
    return MaterialApp.router(
      title: 'KOSKITA',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: themeProvider.themeMode,
      routerConfig: widget.router,
    );
  }
}
