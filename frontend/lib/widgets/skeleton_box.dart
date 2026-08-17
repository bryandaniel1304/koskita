import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

/// Kotak shimmer sederhana untuk skeleton loading -- dipakai gantikan
/// CircularProgressIndicator polos di tempat yang menampilkan daftar/kartu,
/// supaya kelihatan konten "sedang muncul" bukan sekadar layar kosong+spinner.
class SkeletonBox extends StatelessWidget {
  final double width;
  final double height;
  final BorderRadius? borderRadius;

  const SkeletonBox({super.key, this.width = double.infinity, required this.height, this.borderRadius});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final base = isDark ? const Color(0xFF1E293B) : const Color(0xFFEDF1F7);
    final highlight = isDark ? const Color(0xFF283245) : Colors.white;

    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(color: base, borderRadius: borderRadius ?? BorderRadius.circular(14)),
    ).animate(onPlay: (c) => c.repeat()).shimmer(duration: 1200.ms, color: highlight.withValues(alpha: 0.6));
  }
}

/// Skeleton kartu kos -- dipakai di Home/Katalog/Favorit selagi data
/// pertama kali di-fetch, bentuknya meniru KosCard sungguhan.
class SkeletonKosCard extends StatelessWidget {
  const SkeletonKosCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SkeletonBox(width: 88, height: 88, borderRadius: BorderRadius.circular(14)),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SkeletonBox(height: 14, width: 160),
                const SizedBox(height: 8),
                const SkeletonBox(height: 11, width: 100),
                const SizedBox(height: 12),
                const SkeletonBox(height: 13, width: 90),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
