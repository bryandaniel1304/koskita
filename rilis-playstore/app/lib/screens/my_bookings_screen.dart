import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/booking_provider.dart';
import '../models/booking.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../config/app_theme.dart';

class MyBookingsScreen extends StatefulWidget {
  const MyBookingsScreen({super.key});

  @override
  State<MyBookingsScreen> createState() => _MyBookingsScreenState();
}

class _MyBookingsScreenState extends State<MyBookingsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<BookingProvider>(context, listen: false).fetchBookings();
    });
  }

  static const _statusLabel = {
    'pending': 'Menunggu Konfirmasi',
    'confirmed': 'Dikonfirmasi',
    'rejected': 'Ditolak',
    'cancelled': 'Dibatalkan',
    'completed': 'Selesai',
  };

  static const _statusColor = {
    'pending': AppTheme.warning,
    'confirmed': AppTheme.success,
    'rejected': AppTheme.danger,
    'cancelled': Color(0xFF94A3B8),
    'completed': AppTheme.primary,
  };

  static const _statusIcon = {
    'pending': Icons.hourglass_top_rounded,
    'confirmed': Icons.check_circle_rounded,
    'rejected': Icons.cancel_rounded,
    'cancelled': Icons.block_rounded,
    'completed': Icons.flag_circle_rounded,
  };

  Future<void> _cancel(Booking booking) async {
    final provider = Provider.of<BookingProvider>(context, listen: false);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Batalkan Booking?'),
        content: Text('Pengajuan booking untuk "${booking.kos?.name ?? 'kos ini'}" akan dibatalkan.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Tidak')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Ya, Batalkan')),
        ],
      ),
    );
    if (confirmed != true) return;

    final success = await provider.cancelBooking(booking.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Booking dibatalkan.' : 'Gagal membatalkan booking.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<BookingProvider>(context);
    final dateFormat = DateFormat('d MMM yyyy', 'id_ID');

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Booking Saya')),
      body: RefreshIndicator(
        onRefresh: () => provider.fetchBookings(),
        child: Builder(builder: (context) {
          if (provider.isLoading && provider.bookings.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                SkeletonBox(height: 110, borderRadius: BorderRadius.all(Radius.circular(18))),
                SizedBox(height: 14),
                SkeletonBox(height: 110, borderRadius: BorderRadius.all(Radius.circular(18))),
              ],
            );
          }
          if (provider.errorMessage != null && provider.bookings.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: provider.errorMessage!, onRetry: () => provider.fetchBookings()),
              ],
            );
          }
          if (provider.bookings.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada pengajuan booking.\nAjukan booking dari halaman detail kos yang kamu suka.',
                  icon: Icons.calendar_today_outlined,
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.all(20),
            itemCount: provider.bookings.length,
            itemBuilder: (context, index) {
              final booking = provider.bookings[index];
              final color = _statusColor[booking.status] ?? Colors.grey;
              return Container(
                margin: const EdgeInsets.only(bottom: 14),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: AppTheme.softShadow(opacity: 0.05),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(booking.kos?.name ?? '(kos tidak tersedia)', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(_statusIcon[booking.status] ?? Icons.info_rounded, size: 12, color: color),
                              const SizedBox(width: 4),
                              Text(_statusLabel[booking.status] ?? booking.status, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800)),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        const Icon(Icons.event_rounded, size: 13, color: AppTheme.muted),
                        const SizedBox(width: 4),
                        Text('Mulai ${dateFormat.format(booking.startDate)} · ${booking.durationMonths} bulan', style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                    if (booking.status != 'rejected' && booking.status != 'cancelled') ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(
                            booking.isPaid ? Icons.check_circle_rounded : Icons.hourglass_bottom_rounded,
                            size: 13,
                            color: booking.isPaid ? AppTheme.success : AppTheme.muted,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            booking.isPaid ? 'Sudah dibayar' : 'Belum dibayar ke pemilik',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: booking.isPaid ? AppTheme.success : AppTheme.muted,
                                  fontWeight: booking.isPaid ? FontWeight.w700 : FontWeight.w500,
                                ),
                          ),
                        ],
                      ),
                    ],
                    if (booking.notes != null && booking.notes!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Catatan: ${booking.notes}', style: Theme.of(context).textTheme.bodySmall),
                    ],
                    if (booking.adminNote != null && booking.adminNote!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Catatan Admin: ${booking.adminNote}', style: const TextStyle(fontSize: 12, color: AppTheme.primary, fontStyle: FontStyle.italic, fontWeight: FontWeight.w600)),
                    ],
                    if (booking.status != 'rejected' && booking.status != 'cancelled' && booking.kos?.ownerId != null) ...[
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          TextButton.icon(
                            onPressed: () => context.push(
                              '/messages/${booking.kos!.ownerId}?kos_id=${booking.kosId}',
                              extra: booking.kos?.ownerName,
                            ),
                            icon: const Icon(Icons.chat_bubble_outline_rounded, size: 16),
                            label: const Text('Chat Pemilik', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
                            style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), minimumSize: Size.zero),
                          ),
                          if (booking.status == 'pending' || booking.status == 'confirmed')
                            OutlinedButton(
                              onPressed: () => _cancel(booking),
                              style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
                              child: const Text('Batalkan'),
                            ),
                        ],
                      ),
                    ] else if (booking.status == 'pending' || booking.status == 'confirmed') ...[
                      const SizedBox(height: 12),
                      Align(
                        alignment: Alignment.centerRight,
                        child: OutlinedButton(
                          onPressed: () => _cancel(booking),
                          style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
                          child: const Text('Batalkan'),
                        ),
                      ),
                    ],
                  ],
                ),
              ).animate(delay: (index.clamp(0, 6) * 45).ms).fadeIn(duration: 260.ms);
            },
          );
        }),
      ),
    );
  }
}
