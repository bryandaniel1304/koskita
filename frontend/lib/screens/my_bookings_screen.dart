import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../providers/booking_provider.dart';
import '../models/booking.dart';
import '../widgets/error_state.dart';

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
    'pending': Color(0xFFF59E0B),
    'confirmed': Color(0xFF10B981),
    'rejected': Color(0xFFEF4444),
    'cancelled': Color(0xFF94A3B8),
    'completed': Color(0xFF4F46E5),
  };

  Future<void> _cancel(Booking booking) async {
    final provider = Provider.of<BookingProvider>(context, listen: false);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
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
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Booking Saya', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
      ),
      body: RefreshIndicator(
        onRefresh: () => provider.fetchBookings(),
        child: Builder(builder: (context) {
          if (provider.isLoading && provider.bookings.isEmpty) {
            return const Center(child: CircularProgressIndicator());
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
                margin: const EdgeInsets.only(bottom: 16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 6, offset: const Offset(0, 2))],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            booking.kos?.name ?? '(kos tidak tersedia)',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A)),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                          child: Text(
                            _statusLabel[booking.status] ?? booking.status,
                            style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text('Mulai ${dateFormat.format(booking.startDate)} · ${booking.durationMonths} bulan',
                        style: const TextStyle(fontSize: 13, color: Colors.grey)),
                    if (booking.notes != null && booking.notes!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Catatan: ${booking.notes}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                    if (booking.adminNote != null && booking.adminNote!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Catatan Admin: ${booking.adminNote}',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF4F46E5), fontStyle: FontStyle.italic)),
                    ],
                    if (booking.status == 'pending' || booking.status == 'confirmed') ...[
                      const SizedBox(height: 12),
                      Align(
                        alignment: Alignment.centerRight,
                        child: OutlinedButton(
                          onPressed: () => _cancel(booking),
                          style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFEF4444)),
                          child: const Text('Batalkan'),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            },
          );
        }),
      ),
    );
  }
}
