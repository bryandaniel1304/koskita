import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../providers/owner_kos_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/message_provider.dart';
import '../../models/kos.dart';
import '../../widgets/error_state.dart';
import '../../widgets/skeleton_box.dart';
import '../../widgets/onboarding_tips_sheet.dart';
import '../../widgets/changelog_sheet.dart';
import '../../config/app_theme.dart';

class OwnerKosListScreen extends StatefulWidget {
  const OwnerKosListScreen({super.key});

  @override
  State<OwnerKosListScreen> createState() => _OwnerKosListScreenState();
}

class _OwnerKosListScreenState extends State<OwnerKosListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = Provider.of<OwnerKosProvider>(context, listen: false);
      provider.fetchKoses();
      provider.fetchMeta();
      provider.fetchBookings();
      Provider.of<NotificationProvider>(context, listen: false).fetchNotifications();
      Provider.of<MessageProvider>(context, listen: false).fetchUnreadCount();
      OnboardingTipsSheet.showOnceIfNeeded(
        context: context,
        storageKey: 'onboarding_tips_owner_v1',
        headline: 'Selamat Datang, Pemilik Kos',
        tips: const [
          OnboardingTip(
            icon: Icons.bar_chart_rounded,
            title: 'Rekomendasi Penyewa & Analitik',
            description: 'Lihat siapa yang paling cocok dengan kosmu, dan pantau corong konversi lewat Portal Pemilik di web.',
          ),
          OnboardingTip(
            icon: Icons.verified_user_rounded,
            title: 'Verifikasi & QRIS',
            description: 'Kirim dokumen identitas dan unggah QRIS dari halaman Profil supaya penyewa lebih percaya.',
          ),
          OnboardingTip(
            icon: Icons.chat_bubble_rounded,
            title: 'Balas Cepat, Naikkan Kepercayaan',
            description: 'Pakai template balasan cepat di chat -- makin cepat responsmu, makin menonjol kosmu di pencarian.',
          ),
        ],
      );
      ChangelogSheet.maybeShow(context);
    });
  }

  Future<void> _confirmDelete(Kos kos) async {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Hapus Kos?'),
        content: Text('"${kos.name}" akan dihapus permanen beserta seluruh fotonya.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: AppTheme.danger),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    final success = await provider.deleteKos(kos.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Kos dihapus.' : 'Gagal menghapus kos.')),
    );
  }

  String _formatPrice(int price) => 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bulan';

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<OwnerKosProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Kos Saya'),
        actions: [
          Consumer<MessageProvider>(
            builder: (context, msg, _) => Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  icon: const Icon(Icons.chat_bubble_outline_rounded),
                  tooltip: 'Pesan',
                  onPressed: () => context.push('/messages'),
                ),
                if (msg.unreadCount > 0)
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      width: 9,
                      height: 9,
                      decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                    ),
                  ),
              ],
            ),
          ),
          Consumer<NotificationProvider>(
            builder: (context, notif, _) => Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  icon: const Icon(Icons.notifications_outlined),
                  tooltip: 'Notifikasi',
                  onPressed: () => context.push('/notifications'),
                ),
                if (notif.notifications.isNotEmpty)
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      width: 9,
                      height: 9,
                      decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/owner/koses/new'),
        backgroundColor: AppTheme.primary,
        icon: const Icon(Icons.add_rounded, color: Colors.white),
        label: const Text('Tambah Kos', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: RefreshIndicator(
        onRefresh: () => Future.wait([provider.fetchKoses(), provider.fetchBookings()]),
        child: Builder(builder: (context) {
          if (provider.isLoading && provider.koses.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                SkeletonBox(height: 90, borderRadius: BorderRadius.all(Radius.circular(18))),
                SizedBox(height: 20),
                SkeletonKosCard(),
                SizedBox(height: 14),
                SkeletonKosCard(),
              ],
            );
          }
          if (provider.errorMessage != null && provider.koses.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: provider.errorMessage!, onRetry: () => provider.fetchKoses()),
              ],
            );
          }
          if (provider.koses.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada kos yang kamu daftarkan.\nTekan "Tambah Kos" untuk mulai.',
                  icon: Icons.home_work_outlined,
                ),
              ],
            );
          }

          final ratedKoses = provider.koses.where((k) => k.averageReviewRating != null);
          final avgRatingAll = ratedKoses.isEmpty
              ? null
              : ratedKoses.map((k) => k.averageReviewRating!).reduce((a, b) => a + b) / ratedKoses.length;

          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
            itemCount: provider.koses.length + 1,
            itemBuilder: (context, index) {
              if (index == 0) {
                return _DashboardSummary(
                  totalKos: provider.koses.length,
                  pendingBookings: provider.pendingBookingsCount,
                  avgRating: avgRatingAll,
                );
              }
              final kos = provider.koses[index - 1];
              return Container(
                margin: const EdgeInsets.only(bottom: 14),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: AppTheme.softShadow(opacity: 0.05),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(20),
                  onTap: () => context.push('/owner/koses/${kos.id}', extra: kos),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: CachedNetworkImage(
                            imageUrl: kos.coverImage,
                            width: 84,
                            height: 84,
                            fit: BoxFit.cover,
                            placeholder: (c, u) => const SkeletonBox(width: 84, height: 84),
                            errorWidget: (c, u, e) => Container(width: 84, height: 84, color: Colors.grey[200], child: const Icon(Icons.image_outlined, color: Colors.grey)),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(kos.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                              const SizedBox(height: 4),
                              Text(kos.location, style: Theme.of(context).textTheme.bodySmall),
                              const SizedBox(height: 6),
                              Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: AppTheme.primary)),
                              const SizedBox(height: 6),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: (kos.availableRooms > 0 ? AppTheme.success : AppTheme.danger).withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Text(
                                      '${kos.occupiedRooms}/${kos.totalRooms} kamar terisi',
                                      style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: kos.availableRooms > 0 ? const Color(0xFF15803D) : const Color(0xFFB91C1C)),
                                    ),
                                  ),
                                  if (kos.averageReviewRating != null)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(color: AppTheme.warning.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(20)),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(Icons.star_rounded, size: 12, color: Color(0xFFD97706)),
                                          Text(' ${kos.averageReviewRating!.toStringAsFixed(1)}', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: Color(0xFF92400E))),
                                        ],
                                      ),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        PopupMenuButton<String>(
                          icon: const Icon(Icons.more_vert_rounded, color: AppTheme.muted),
                          tooltip: 'Opsi kos ${kos.name}',
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          onSelected: (value) {
                            if (value == 'edit') {
                              context.push('/owner/koses/${kos.id}/edit', extra: kos);
                            } else if (value == 'delete') {
                              _confirmDelete(kos);
                            }
                          },
                          itemBuilder: (context) => const [
                            PopupMenuItem(value: 'edit', child: Text('Edit')),
                            PopupMenuItem(value: 'delete', child: Text('Hapus', style: TextStyle(color: AppTheme.danger))),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ).animate(delay: (index.clamp(0, 6) * 45).ms).fadeIn(duration: 260.ms);
            },
          );
        }),
      ),
    );
  }
}

/// Ringkasan ala dashboard host modern -- total kos, booking yang butuh
/// perhatian, dan rata-rata rating lintas semua kos milik pemilik.
class _DashboardSummary extends StatelessWidget {
  final int totalKos;
  final int pendingBookings;
  final double? avgRating;

  const _DashboardSummary({required this.totalKos, required this.pendingBookings, required this.avgRating});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: AppTheme.primaryGradient,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppTheme.glowShadow(AppTheme.primary, opacity: 0.25),
      ),
      child: Row(
        children: [
          Expanded(child: _stat('Total Kos', '$totalKos', Icons.home_work_rounded)),
          Container(width: 1, height: 40, color: Colors.white24),
          Expanded(
            child: _stat(
              'Booking Menunggu',
              '$pendingBookings',
              Icons.pending_actions_rounded,
              highlight: pendingBookings > 0,
            ),
          ),
          Container(width: 1, height: 40, color: Colors.white24),
          Expanded(child: _stat('Rating Rata-rata', avgRating != null ? avgRating!.toStringAsFixed(1) : '—', Icons.star_rounded)),
        ],
      ),
    ).animate().fadeIn(duration: 300.ms).slideY(begin: 0.06, end: 0);
  }

  Widget _stat(String label, String value, IconData icon, {bool highlight = false}) {
    return Column(
      children: [
        Icon(icon, color: highlight ? const Color(0xFFFDE047) : Colors.white, size: 22),
        const SizedBox(height: 6),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
        const SizedBox(height: 2),
        Text(label, textAlign: TextAlign.center, style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 10.5, fontWeight: FontWeight.w600)),
      ],
    );
  }
}
