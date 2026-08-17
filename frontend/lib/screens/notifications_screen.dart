import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/notification_provider.dart';
import '../providers/auth_provider.dart';
import '../models/app_notification.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../config/app_theme.dart';

/// Feed notifikasi in-app (status booking berubah untuk penyewa, booking
/// baru/ulasan baru untuk pemilik) -- di-polling lewat pull-to-refresh,
/// bukan push sungguhan (lihat catatan di NotificationController backend).
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<NotificationProvider>(context, listen: false).fetchNotifications();
    });
  }

  IconData _iconFor(AppNotification n) {
    switch (n.type) {
      case 'booking_confirmed':
        return Icons.check_circle_rounded;
      case 'booking_rejected':
      case 'booking_cancelled':
        return Icons.cancel_rounded;
      case 'booking_completed':
        return Icons.flag_circle_rounded;
      case 'booking_pending':
        return Icons.hourglass_top_rounded;
      case 'new_review':
      case 'review_prompt':
        return Icons.rate_review_rounded;
      default:
        return Icons.notifications_rounded;
    }
  }

  Color _colorFor(AppNotification n) {
    if (n.isPositive) return AppTheme.success;
    if (n.isNegative) return AppTheme.danger;
    return AppTheme.primary;
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<NotificationProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Notifikasi')),
      body: RefreshIndicator(
        onRefresh: () => provider.fetchNotifications(),
        child: Builder(builder: (context) {
          if (provider.isLoading && provider.notifications.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                SkeletonBox(height: 76, borderRadius: BorderRadius.all(Radius.circular(16))),
                SizedBox(height: 12),
                SkeletonBox(height: 76, borderRadius: BorderRadius.all(Radius.circular(16))),
                SizedBox(height: 12),
                SkeletonBox(height: 76, borderRadius: BorderRadius.all(Radius.circular(16))),
              ],
            );
          }
          if (provider.errorMessage != null && provider.notifications.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: provider.errorMessage!, onRetry: () => provider.fetchNotifications()),
              ],
            );
          }
          if (provider.notifications.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada notifikasi.\nAktivitas terbaru (booking, ulasan) akan muncul di sini.',
                  icon: Icons.notifications_none_rounded,
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.all(20),
            itemCount: provider.notifications.length,
            itemBuilder: (context, index) {
              final n = provider.notifications[index];
              final color = _colorFor(n);
              final isOwner = Provider.of<AuthProvider>(context, listen: false).user?.role == 'owner';
              return GestureDetector(
                onTap: n.kosId != null
                    ? () => context.push(isOwner ? '/owner/koses/${n.kosId}' : '/kos/${n.kosId}')
                    : null,
                child: Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: AppTheme.softShadow(opacity: 0.05),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(9),
                        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                        child: Icon(_iconFor(n), color: color, size: 20),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(n.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                            const SizedBox(height: 3),
                            Text(n.message, style: Theme.of(context).textTheme.bodySmall),
                            const SizedBox(height: 6),
                            Text(
                              DateFormat('d MMM yyyy, HH:mm', 'id_ID').format(n.createdAt),
                              style: TextStyle(fontSize: 10.5, color: Theme.of(context).textTheme.bodySmall?.color?.withValues(alpha: 0.7)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ).animate(delay: (index.clamp(0, 8) * 40).ms).fadeIn(duration: 240.ms);
            },
          );
        }),
      ),
    );
  }
}
