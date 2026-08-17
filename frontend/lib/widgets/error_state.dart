import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../widgets/premium_button.dart';
import '../widgets/empty_state_illustration.dart';

/// Tampilan error/empty state yang seragam dipakai di seluruh app saat
/// fetch gagal atau data kosong, lengkap dengan tombol "Coba Lagi" opsional.
class ErrorStateView extends StatelessWidget {
  final String message;
  final IconData icon;
  final VoidCallback? onRetry;
  final bool _isEmptyVariant;

  const ErrorStateView({
    super.key,
    required this.message,
    this.icon = Icons.wifi_off_rounded,
    this.onRetry,
  }) : _isEmptyVariant = false;

  const ErrorStateView.empty({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.onRetry,
  }) : _isEmptyVariant = true;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Data kosong (belum pernah ada apa-apa) pakai ilustrasi custom;
            // kegagalan jaringan/server tetap pakai ikon polos -- lebih
            // netral untuk kondisi "sesuatu salah", bukan "belum ada apa-apa".
            (_isEmptyVariant
                ? EmptyStateIllustration(icon: icon, size: 128)
                : Container(
                    width: 92,
                    height: 92,
                    decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.08), shape: BoxShape.circle),
                    alignment: Alignment.center,
                    child: Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.12), shape: BoxShape.circle),
                      child: Icon(icon, size: 32, color: AppTheme.primaryLight),
                    ),
                  )).animate().scale(duration: 380.ms, curve: Curves.easeOutBack, begin: const Offset(0.6, 0.6)).fadeIn(duration: 280.ms),
            const SizedBox(height: 22),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ).animate(delay: 100.ms).fadeIn(duration: 320.ms).slideY(begin: 0.15, end: 0),
            if (onRetry != null) ...[
              const SizedBox(height: 22),
              PremiumButton(label: 'Coba Lagi', icon: Icons.refresh_rounded, onPressed: onRetry, expand: false)
                  .animate(delay: 180.ms)
                  .fadeIn(duration: 320.ms),
            ],
          ],
        ),
      ),
    );
  }
}
