import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../models/kos.dart';
import '../screens/splash_screen.dart';
import '../screens/walkthrough_screen.dart';
import '../screens/login_register_screen.dart';
import '../screens/two_factor_screen.dart';
import '../screens/onboarding_screen.dart';
import '../screens/home_screen.dart';
import '../screens/favorites_screen.dart';
import '../screens/my_bookings_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/kos_detail_screen.dart';
import '../screens/booking_form_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/conversations_screen.dart';
import '../screens/chat_screen.dart';
import '../screens/map_browse_screen.dart';
import '../screens/compare_screen.dart';
import '../screens/main_shell.dart';
import '../screens/owner/owner_shell.dart';
import '../screens/owner/owner_kos_list_screen.dart';
import '../screens/owner/owner_kos_form_screen.dart';
import '../screens/owner/owner_kos_detail_screen.dart';
import '../screens/owner/owner_bookings_screen.dart';
import '../screens/owner/owner_profile_screen.dart';

/// `extra` di go_router cuma nitip referensi objek Dart di memori -- TIDAK
/// tersimpan/ter-serialize. Kalau proses Android di-reclaim OS lalu di-resume
/// (device di-lock lama, dsb.), go_router bisa replay rute terakhir dari
/// state yang dipulihkan tanpa `extra` yang valid (jadi Map hasil decode
/// JSON, bukan objek `Kos` lagi) -- `as Kos` langsung meledak (TypeError)
/// dan bikin seluruh app crash ke red screen. Helper ini jaga-jaga di semua
/// rute yang menerima `extra` supaya tidak percaya begitu saja tipe datanya.
Kos? _extraAsKos(Object? extra) => extra is Kos ? extra : null;

List<Kos> _extraAsKosList(Object? extra) =>
    extra is List ? extra.whereType<Kos>().toList() : const [];

/// Redirect memakai [authProvider] (bukan [BuildContext.read]) supaya logika
/// tetap konsisten walau dipanggil sebelum widget tree penuh terbangun, dan
/// `refreshListenable` di bawah membuat router otomatis re-evaluate redirect
/// setiap AuthProvider berubah (login, logout, atau sesi kedaluwarsa/401).
GoRouter buildRouter(AuthProvider authProvider) {
  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: authProvider,
    redirect: (context, state) {
      final loggedIn = authProvider.isAuthenticated;
      final path = state.matchedLocation;
      final isOwner = authProvider.user?.role == 'owner';
      final ownerHome = '/owner/koses';

      if (path == '/splash' || path == '/walkthrough') return null;
      // Tantangan 2FA terjadi SEBELUM benar-benar login (password sudah
      // benar tapi token belum terbit) -- kalau tidak dikecualikan di
      // sini, guard "belum login -> /login" di bawah langsung lempar
      // balik ke halaman masuk sebelum sempat masukkan kodenya.
      if (path == '/verifikasi-2fa') return null;
      if (!loggedIn && path != '/login') return '/login';
      if (loggedIn && path == '/login') return isOwner ? ownerHome : '/home';

      // Notifikasi & Pesan dipakai kedua peran (isinya beda otomatis
      // mengikuti role di backend) -- dikecualikan dari aturan pemisahan
      // rute di bawah supaya pemilik kos juga bisa buka tanpa terlempar balik.
      if (path == '/notifications' || path == '/messages' || path.startsWith('/messages/')) return null;

      // Pemilik kos tidak punya profil preferensi/rekomendasi penyewa --
      // jauhkan dari rute khusus penyewa, dan sebaliknya.
      if (loggedIn && isOwner && !path.startsWith('/owner')) return ownerHome;
      if (loggedIn && !isOwner && path.startsWith('/owner')) return '/home';

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/walkthrough', builder: (context, state) => const WalkthroughScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginRegisterScreen()),
      GoRoute(path: '/verifikasi-2fa', builder: (context, state) => const TwoFactorScreen()),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => OnboardingScreen(
          fromRegistration: state.uri.queryParameters['from'] == 'register',
        ),
      ),
      GoRoute(
        path: '/kos/:id',
        pageBuilder: (context, state) => MaterialPage(
          key: UniqueKey(),
          child: KosDetailScreen(kosId: int.parse(state.pathParameters['id']!)),
        ),
      ),
      GoRoute(
        path: '/booking/new',
        // Kos wajib ada -- kalau `extra` rusak/hilang (lihat catatan
        // `_extraAsKos` di atas), gak ada `kosId` di path buat fallback
        // fetch, jadi paling aman lempar balik ke beranda daripada crash.
        redirect: (context, state) => state.extra is Kos ? null : '/home',
        builder: (context, state) => BookingFormScreen(kos: state.extra as Kos),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/messages',
        builder: (context, state) => const ConversationsScreen(),
      ),
      GoRoute(
        path: '/messages/:userId',
        builder: (context, state) => ChatScreen(
          partnerId: int.parse(state.pathParameters['userId']!),
          partnerName: state.extra is String ? state.extra as String : null,
          kosId: int.tryParse(state.uri.queryParameters['kos_id'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/map',
        builder: (context, state) => const MapBrowseScreen(),
      ),
      GoRoute(
        path: '/compare',
        // Butuh minimal 2 kos yang valid buat dibandingkan -- kalau
        // `extra` rusak/hilang, lempar balik ke Favorit daripada crash.
        redirect: (context, state) => _extraAsKosList(state.extra).length >= 2 ? null : '/favorites',
        builder: (context, state) => CompareScreen(koses: _extraAsKosList(state.extra)),
      ),
      GoRoute(
        path: '/owner/koses/new',
        builder: (context, state) => const OwnerKosFormScreen(),
      ),
      GoRoute(
        path: '/owner/koses/:id/edit',
        pageBuilder: (context, state) => MaterialPage(
          key: UniqueKey(),
          child: OwnerKosFormScreen(kos: _extraAsKos(state.extra)),
        ),
      ),
      GoRoute(
        path: '/owner/koses/:id',
        pageBuilder: (context, state) => MaterialPage(
          key: UniqueKey(),
          child: OwnerKosDetailScreen(
            kosId: int.parse(state.pathParameters['id']!),
            kos: _extraAsKos(state.extra),
          ),
        ),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => MainShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/home', builder: (context, state) => const HomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/favorites', builder: (context, state) => const FavoritesScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/bookings', builder: (context, state) => const MyBookingsScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
          ]),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => OwnerShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/owner/koses', builder: (context, state) => const OwnerKosListScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/owner/bookings', builder: (context, state) => const OwnerBookingsScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/owner/profile', builder: (context, state) => const OwnerProfileScreen()),
          ]),
        ],
      ),
    ],
  );
}
