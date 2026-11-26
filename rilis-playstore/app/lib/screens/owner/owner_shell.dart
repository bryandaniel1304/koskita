import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../widgets/trendy_bottom_nav.dart';
import '../../widgets/online_nanny_bubble.dart';
import '../../providers/owner_kos_provider.dart';

/// Shell navigasi khusus akun Penyedia Kos -- terpisah total dari MainShell
/// milik penyewa (tab & tujuan layarnya berbeda), tapi tetap bawa bubble
/// Online Nanny (jawabannya otomatis disesuaikan untuk pemilik kos lewat
/// role-check di backend, lihat OnlineNannyService::respondToOwner).
class OwnerShell extends StatelessWidget {
  final StatefulNavigationShell navigationShell;

  const OwnerShell({super.key, required this.navigationShell});

  @override
  Widget build(BuildContext context) {
    final pendingCount = context.watch<OwnerKosProvider>().pendingBookingsCount;

    final items = [
      const NavItemData(icon: Icons.home_work_outlined, activeIcon: Icons.home_work_rounded, label: 'Kos Saya'),
      NavItemData(
        icon: Icons.calendar_month_outlined,
        activeIcon: Icons.calendar_month_rounded,
        label: 'Booking',
        badgeCount: pendingCount,
      ),
      const NavItemData(icon: Icons.person_outline_rounded, activeIcon: Icons.person_rounded, label: 'Profil'),
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Stack(
        children: [
          navigationShell,
          // Pojok kiri bawah -- pojok kanan bawah dipakai tombol aksi
          // (mis. "Tambah Kos") di beberapa layar pemilik, jadi digeser ke
          // kiri supaya tidak numpuk/tabrakan.
          const Positioned(left: 16, bottom: 16, child: OnlineNannyBubble()),
        ],
      ),
      bottomNavigationBar: TrendyBottomNav(
        currentIndex: navigationShell.currentIndex,
        items: items,
        onTap: (index) => navigationShell.goBranch(
          index,
          initialLocation: index == navigationShell.currentIndex,
        ),
      ),
    );
  }
}
