import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../providers/owner_kos_provider.dart';
import '../../widgets/error_state.dart';
import '../../widgets/skeleton_box.dart';
import '../../config/app_theme.dart';

/// Semua booking yang masuk lintas kos milik pemilik -- versi ringkas dari
/// tab "Booking Masuk" di detail kos, supaya pemilik bisa pantau semuanya
/// dari satu tempat tanpa buka tiap kos satu-satu.
class OwnerBookingsScreen extends StatefulWidget {
  const OwnerBookingsScreen({super.key});

  @override
  State<OwnerBookingsScreen> createState() => _OwnerBookingsScreenState();
}

class _OwnerBookingsScreenState extends State<OwnerBookingsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<OwnerKosProvider>(context, listen: false).fetchBookings();
    });
  }

  static const _statusLabel = {
    'pending': 'Menunggu',
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

  Future<void> _respond(int id, String status) async {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final success = await provider.updateBookingStatus(id, status);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Booking diperbarui.' : 'Gagal memperbarui booking.')),
    );
  }

  Future<void> _togglePayment(int id, String currentStatus) async {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final next = currentStatus == 'paid' ? 'unpaid' : 'paid';
    final success = await provider.updateBookingPaymentStatus(id, next);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Status pembayaran diperbarui.' : 'Gagal memperbarui status pembayaran.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<OwnerKosProvider>(context);
    final dateFormat = DateFormat('d MMM yyyy', 'id_ID');

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Booking Masuk')),
      body: RefreshIndicator(
        onRefresh: () => provider.fetchBookings(),
        child: Builder(builder: (context) {
          if (provider.isBookingsLoading && provider.bookings.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                SkeletonBox(height: 110, borderRadius: BorderRadius.all(Radius.circular(18))),
                SizedBox(height: 14),
                SkeletonBox(height: 110, borderRadius: BorderRadius.all(Radius.circular(18))),
              ],
            );
          }
          if (provider.bookingsErrorMessage != null && provider.bookings.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: provider.bookingsErrorMessage!, onRetry: () => provider.fetchBookings()),
              ],
            );
          }
          if (provider.bookings.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada booking masuk.\nBooking dari penyewa yang tertarik akan otomatis muncul di sini.',
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
                          child: Text(booking.kos?.name ?? '(kos tidak tersedia)', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                          child: Text(_statusLabel[booking.status] ?? booking.status, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(Icons.event_rounded, size: 13, color: AppTheme.muted),
                        const SizedBox(width: 4),
                        Text('Mulai ${dateFormat.format(booking.startDate)} · ${booking.durationMonths} bulan', style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                    if (booking.userName != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Row(
                              children: [
                                const Icon(Icons.person_outline_rounded, size: 13, color: AppTheme.muted),
                                const SizedBox(width: 4),
                                Flexible(
                                  child: Text(booking.userName!, style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis),
                                ),
                              ],
                            ),
                          ),
                          if (booking.userId != null)
                            Tooltip(
                              message: 'Chat ${booking.userName ?? 'penyewa'}',
                              child: InkWell(
                                borderRadius: BorderRadius.circular(20),
                                onTap: () => context.push(
                                  '/messages/${booking.userId}?kos_id=${booking.kosId}',
                                  extra: booking.userName,
                                ),
                                child: const Padding(
                                  padding: EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                                  child: Icon(Icons.chat_bubble_outline_rounded, size: 16, color: AppTheme.primary),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                    if (booking.status != 'rejected' && booking.status != 'cancelled') ...[
                      const SizedBox(height: 10),
                      InkWell(
                        borderRadius: BorderRadius.circular(20),
                        onTap: () => _togglePayment(booking.id, booking.paymentStatus),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: (booking.isPaid ? AppTheme.success : AppTheme.warning).withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                booking.isPaid ? Icons.check_circle_rounded : Icons.hourglass_bottom_rounded,
                                size: 13,
                                color: booking.isPaid ? AppTheme.success : AppTheme.warning,
                              ),
                              const SizedBox(width: 5),
                              Text(
                                booking.isPaid ? 'Sudah Dibayar' : 'Tandai Sudah Dibayar',
                                style: TextStyle(
                                  color: booking.isPaid ? AppTheme.success : AppTheme.warning,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                    if (booking.status == 'pending') ...[
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () => _respond(booking.id, 'rejected'),
                              style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
                              child: const Text('Tolak'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: ElevatedButton(
                              onPressed: () => _respond(booking.id, 'confirmed'),
                              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.success),
                              child: const Text('Konfirmasi'),
                            ),
                          ),
                        ],
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
