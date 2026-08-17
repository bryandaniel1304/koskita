import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../widgets/trendy_bottom_nav.dart';
import '../widgets/online_nanny_bubble.dart';

/// Shell dengan bottom navigation bar "floating pill" yang membungkus 4 tab
/// utama app: Beranda, Favorit, Booking, dan Profil, plus bubble Online
/// Nanny yang mengambang di semua tab.
class MainShell extends StatelessWidget {
  final StatefulNavigationShell navigationShell;

  const MainShell({super.key, required this.navigationShell});

  static const _items = [
    NavItemData(icon: Icons.home_outlined, activeIcon: Icons.home_rounded, label: 'Beranda'),
    NavItemData(icon: Icons.favorite_outline_rounded, activeIcon: Icons.favorite_rounded, label: 'Favorit'),
    NavItemData(icon: Icons.calendar_month_outlined, activeIcon: Icons.calendar_month_rounded, label: 'Booking'),
    NavItemData(icon: Icons.person_outline_rounded, activeIcon: Icons.person_rounded, label: 'Profil'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Stack(
        children: [
          navigationShell,
          const Positioned(
            right: 16,
            bottom: 16,
            child: OnlineNannyBubble(),
          ),
        ],
      ),
      bottomNavigationBar: TrendyBottomNav(
        currentIndex: navigationShell.currentIndex,
        items: _items,
        onTap: (index) => navigationShell.goBranch(
          index,
          initialLocation: index == navigationShell.currentIndex,
        ),
      ),
    );
  }
}
