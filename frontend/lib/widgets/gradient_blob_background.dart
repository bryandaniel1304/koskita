import 'dart:ui';
import 'package:flutter/material.dart';
import '../config/app_theme.dart';

/// Latar dekoratif "blob" gradient blur -- dipakai di layar splash,
/// walkthrough, dan auth supaya tidak terasa kosong/flat, ciri khas
/// tampilan premium modern (mirip banyak app fintech/travel kekinian).
class GradientBlobBackground extends StatelessWidget {
  final Widget child;
  final Color? tint;

  const GradientBlobBackground({super.key, required this.child, this.tint});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final color = tint ?? AppTheme.primaryLight;

    return Stack(
      fit: StackFit.expand,
      children: [
        Container(color: Theme.of(context).scaffoldBackgroundColor),
        Positioned(
          top: -90,
          right: -70,
          child: _blob(220, color.withValues(alpha: isDark ? 0.28 : 0.30)),
        ),
        Positioned(
          bottom: -110,
          left: -80,
          child: _blob(260, AppTheme.primary.withValues(alpha: isDark ? 0.22 : 0.16)),
        ),
        child,
      ],
    );
  }

  Widget _blob(double size, Color color) {
    return ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 60, sigmaY: 60),
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
    );
  }
}
