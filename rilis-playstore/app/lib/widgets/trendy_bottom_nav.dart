import 'package:flutter/material.dart';
import '../config/app_theme.dart';

class NavItemData {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  /// Badge angka kecil di pojok ikon (mis. jumlah booking pending) -- null
  /// atau 0 berarti tidak ada badge yang tampil.
  final int? badgeCount;

  const NavItemData({
    required this.icon,
    required this.activeIcon,
    required this.label,
    this.badgeCount,
  });
}

/// Bottom navigation gaya "raised tab" -- item yang aktif jadi bubble bulat
/// bergradasi yang mengambang sedikit keluar dari tepi atas bar (gaya
/// navbar app modern seperti Airbnb), item lain cuma ikon outline + label
/// kecil duduk normal di dalam bar. Mengambang di atas layar dengan
/// bayangan lembut, bukan menempel rata di tepi layar seperti navbar
/// Material default.
class TrendyBottomNav extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final List<NavItemData> items;

  const TrendyBottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
    required this.items,
  });

  // Tinggi kotak luar sengaja lebih besar dari tinggi bar visualnya --
  // ruang ekstra di atas itu yang dipakai bubble item aktif buat
  // "mengambang" keluar dari tepi bar tanpa ke-clip, dan supaya area tap
  // bubble itu tetap ikut terdaftar (lihat kenapa tiap slot item dibuat
  // setinggi kotak luar, bukan cuma setinggi bar, di bawah).
  static const double _outerHeight = 84;
  static const double _barHeight = 56;
  static const double _bubbleSize = 50;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: SizedBox(
        height: _outerHeight,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: _barHeight,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(26),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF355DDB).withValues(alpha: 0.16),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
              ),
            ),
            // Baris item ditaruh setinggi KOTAK LUAR (bukan cuma bar) supaya
            // bubble yang mengambang di atas tepi bar tetap masuk area tap
            // slotnya -- kalau cuma setinggi bar, bagian bubble yang
            // menonjol ke atas jatuh di luar hit-test Row/Expanded dan jadi
            // tidak bisa disentuh tepat di situ.
            Row(
              children: List.generate(items.length, (index) {
                final selected = index == currentIndex;
                final item = items[index];
                return Expanded(
                  // Semantics eksplisit -- GestureDetector polos tidak
                  // otomatis terbaca screen reader sebagai tombol bernama,
                  // cuma "double tap to activate" tanpa label apa-apa.
                  child: Semantics(
                    button: true,
                    selected: selected,
                    label: (item.badgeCount ?? 0) > 0 ? '${item.label}, ${item.badgeCount} menunggu' : item.label,
                    child: GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: () => onTap(index),
                      child: SizedBox(
                        height: _outerHeight,
                        child: ExcludeSemantics(
                          child: selected ? _ActiveBubble(item: item) : _InactiveItem(item: item),
                        ),
                      ),
                    ),
                  ),
                );
              }),
            ),
          ],
        ),
      ),
    );
  }
}

class _ActiveBubble extends StatelessWidget {
  final NavItemData item;
  const _ActiveBubble({required this.item});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.topCenter,
      child: Padding(
        padding: const EdgeInsets.only(
          top: TrendyBottomNav._outerHeight - TrendyBottomNav._barHeight - (TrendyBottomNav._bubbleSize / 2),
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOutCubic,
              width: TrendyBottomNav._bubbleSize,
              height: TrendyBottomNav._bubbleSize,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                shape: BoxShape.circle,
                // Cincin tipis warna dasar bar -- kesannya bubble ini
                // "nongol lewat" lubang di bar, bukan cuma nempel di atasnya.
                border: Border.all(color: Theme.of(context).cardColor, width: 3),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.primary.withValues(alpha: 0.4),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Icon(item.activeIcon, color: Colors.white, size: 22),
            ),
            if ((item.badgeCount ?? 0) > 0)
              Positioned(
                right: -2,
                top: -2,
                child: _BadgeDot(count: item.badgeCount!, ringColor: Theme.of(context).cardColor),
              ),
          ],
        ),
      ),
    );
  }
}

class _InactiveItem extends StatelessWidget {
  final NavItemData item;
  const _InactiveItem({required this.item});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.bottomCenter,
      child: Padding(
        padding: const EdgeInsets.only(bottom: 13),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(item.icon, color: const Color(0xFF94A3B8), size: 21),
                if ((item.badgeCount ?? 0) > 0)
                  Positioned(
                    right: -6,
                    top: -4,
                    child: _BadgeDot(count: item.badgeCount!, ringColor: Theme.of(context).cardColor),
                  ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Color(0xFF94A3B8), fontWeight: FontWeight.w600, fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }
}

class _BadgeDot extends StatelessWidget {
  final int count;
  final Color ringColor;
  const _BadgeDot({required this.count, required this.ringColor});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
      constraints: const BoxConstraints(minWidth: 15),
      decoration: BoxDecoration(
        color: const Color(0xFFF43F5E),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: ringColor, width: 1.5),
      ),
      child: Text(
        count > 9 ? '9+' : '$count',
        textAlign: TextAlign.center,
        style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
      ),
    );
  }
}
