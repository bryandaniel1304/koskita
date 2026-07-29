import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../models/kos.dart';
import '../screens/splash_screen.dart';
import '../screens/login_register_screen.dart';
import '../screens/onboarding_screen.dart';
import '../screens/home_screen.dart';
import '../screens/favorites_screen.dart';
import '../screens/my_bookings_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/kos_detail_screen.dart';
import '../screens/booking_form_screen.dart';
import '../screens/main_shell.dart';

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

      if (path == '/splash') return null;
      if (!loggedIn && path != '/login') return '/login';
      if (loggedIn && path == '/login') return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginRegisterScreen()),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => OnboardingScreen(
          fromRegistration: state.uri.queryParameters['from'] == 'register',
        ),
      ),
      GoRoute(
        path: '/kos/:id',
        builder: (context, state) => KosDetailScreen(kosId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/booking/new',
        builder: (context, state) => BookingFormScreen(kos: state.extra as Kos),
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
    ],
  );
}
