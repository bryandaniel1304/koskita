import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../providers/owner_kos_provider.dart';
import '../../models/kos.dart';
import '../../models/booking.dart';
import '../../models/tenant_match.dart';
import '../../models/owner_kos_stats.dart';
import '../../models/weekly_stat.dart';
import '../../models/review.dart';
import '../../config/app_theme.dart';
import '../../widgets/kos_location_map.dart';
import '../../widgets/weekly_activity_chart.dart';
import '../../widgets/skeleton_box.dart';

/// Detail satu kos milik pemilik: pratinjau lengkap persis yang dilihat
/// calon penyewa ("Info Kos" -- termasuk peta lokasi, karena pemiliklah
/// yang menginput data lokasinya), daftar booking yang masuk, dan
/// "Rekomendasi Penyewa" -- kebalikan dari rekomendasi kos yang dilihat
/// penyewa, di sini pemilik melihat profil penyewa yang paling cocok dengan
/// kos miliknya (hasil ContentBasedFilter::calculateScoresForKos).
class OwnerKosDetailScreen extends StatefulWidget {
  final int kosId;
  final Kos? kos;

  const OwnerKosDetailScreen({super.key, required this.kosId, this.kos});

  @override
  State<OwnerKosDetailScreen> createState() => _OwnerKosDetailScreenState();
}

class _OwnerKosDetailScreenState extends State<OwnerKosDetailScreen> {
  Future<List<TenantMatch>>? _matchesFuture;
  Future<(Kos, OwnerKosStats)>? _detailFuture;
  Future<List<WeeklyStat>>? _analyticsFuture;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = Provider.of<OwnerKosProvider>(context, listen: false);
      if (provider.bookings.isEmpty) provider.fetchBookings();
      setState(() {
        _matchesFuture = provider.fetchMatches(widget.kosId);
        _detailFuture = provider.fetchKosDetail(widget.kosId);
        _analyticsFuture = provider.fetchAnalytics(widget.kosId);
      });
    });
  }

  void _reloadDetail() {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    setState(() => _detailFuture = provider.fetchKosDetail(widget.kosId));
  }

  String _formatRupiah(num value) => 'Rp ${(value / 1000000).toStringAsFixed(1)} jt';

  @override
  Widget build(BuildContext context) {
    final fallbackKos = widget.kos ??
        Provider.of<OwnerKosProvider>(context, listen: false).koses.firstWhere(
              (k) => k.id == widget.kosId,
              orElse: () => Kos(
                id: widget.kosId,
                name: 'Kos',
                price: 0,
                genderType: '',
                location: '',
                distanceToCampus: 0,
                description: '',
                imageUrl: '',
                coverImage: '',
                facilities: const [],
                rules: const [],
                images: const [],
              ),
            );

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        appBar: AppBar(
          title: Text(fallbackKos.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          actions: [
            IconButton(
              icon: const Icon(Icons.edit_outlined),
              tooltip: 'Edit kos',
              onPressed: () async {
                await context.push('/owner/koses/${widget.kosId}/edit', extra: fallbackKos);
                _reloadDetail();
              },
            ),
          ],
          bottom: const TabBar(
            labelColor: Color(0xFF355DDB),
            unselectedLabelColor: Color(0xFF94A3B8),
            indicatorColor: Color(0xFF355DDB),
            isScrollable: true,
            tabs: [
              Tab(text: 'Info Kos'),
              Tab(text: 'Booking Masuk'),
              Tab(text: 'Rekomendasi Penyewa'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _InfoTab(detailFuture: _detailFuture, fallbackKos: fallbackKos, analyticsFuture: _analyticsFuture, onRefresh: () async => _reloadDetail()),
            _BookingTab(kosId: widget.kosId),
            _MatchesTab(matchesFuture: _matchesFuture, formatRupiah: _formatRupiah),
          ],
        ),
      ),
    );
  }
}

/// Pratinjau kos persis yang dilihat calon penyewa (foto, harga, peta,
/// fasilitas/aturan, ulasan publik) DITAMBAH statistik yang cuma pemilik
/// sendiri boleh lihat (jumlah dilihat, difavoritkan, rating privat).
class _InfoTab extends StatelessWidget {
  final Future<(Kos, OwnerKosStats)>? detailFuture;
  final Kos fallbackKos;
  final Future<List<WeeklyStat>>? analyticsFuture;
  final Future<void> Function() onRefresh;

  const _InfoTab({required this.detailFuture, required this.fallbackKos, required this.analyticsFuture, required this.onRefresh});

  String _formatPrice(int price) => 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bulan';

  Future<void> _showReplyDialog(BuildContext context, Review review) async {
    final controller = TextEditingController(text: review.ownerReply ?? '');
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);

    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text('Balas ulasan ${review.userName}'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (review.comment != null && review.comment!.isNotEmpty) ...[
              Text('"${review.comment}"', style: const TextStyle(fontStyle: FontStyle.italic, color: Color(0xFF64748B), fontSize: 13)),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: controller,
              maxLines: 3,
              maxLength: 1000,
              decoration: const InputDecoration(hintText: 'Tulis balasanmu (kosongkan untuk hapus balasan)...'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Simpan')),
        ],
      ),
    );

    if (result != true) return;
    final success = await provider.replyToReview(review.id, controller.text.trim());
    if (success) await onRefresh();
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(success ? 'Balasan tersimpan.' : 'Gagal menyimpan balasan.'), backgroundColor: success ? AppTheme.success : AppTheme.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (detailFuture == null) {
      return const _InfoTabSkeleton();
    }

    return FutureBuilder<(Kos, OwnerKosStats)>(
      future: detailFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const _InfoTabSkeleton();
        }
        if (snapshot.hasError || !snapshot.hasData) {
          return Center(child: Text('Gagal memuat info kos.', style: TextStyle(color: Colors.grey[600])));
        }

        final (kos, stats) = snapshot.data!;
        final images = kos.galleryUrls;

        return RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              if (images.isNotEmpty)
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: SizedBox(
                    height: 180,
                    child: images.length == 1
                        ? CachedNetworkImage(imageUrl: images.first, fit: BoxFit.cover, width: double.infinity)
                        : ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: images.length,
                            separatorBuilder: (_, __) => const SizedBox(width: 8),
                            itemBuilder: (context, i) => ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: CachedNetworkImage(imageUrl: images[i], fit: BoxFit.cover, width: 260),
                            ),
                          ),
                  ),
                ),
              const SizedBox(height: 16),

              // Statistik privat (cuma pemilik yang lihat) -- SEBELUMNYA
              // pakai warna gelap hardcode (0xFF0F172A) yang nyangkut di
              // mode terang (kartu jadi kotak hitam aneh di tengah layar
              // putih). Ganti ke gradient primer yang sama dipakai di kartu
              // ringkasan "Total Kos/Booking Menunggu" (owner_kos_list_
              // screen.dart) -- teks putih di atasnya tetap kebaca jelas di
              // KEDUA mode karena gradient-nya sendiri bukan turunan tema.
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    Expanded(child: _statItem(Icons.visibility_outlined, '${stats.totalViews}', 'Dilihat')),
                    Container(width: 1, height: 32, color: Colors.white24),
                    Expanded(child: _statItem(Icons.favorite_outline_rounded, '${stats.totalFavorites}', 'Favorit')),
                    Container(width: 1, height: 32, color: Colors.white24),
                    Expanded(
                      child: _statItem(
                        Icons.star_outline_rounded,
                        stats.avgRating != null ? stats.avgRating!.toStringAsFixed(1) : '—',
                        '${stats.totalRatings} Rating',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Harga & kamar
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Theme.of(context).dividerTheme.color ?? const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Harga Sewa', style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
                        Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF355DDB))),
                      ],
                    ),
                    const Divider(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Kamar', style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
                        const SizedBox(width: 12),
                        // Flexible -- teks ini cukup panjang, rawan overflow
                        // di skala font sistem besar (aksesibilitas) tanpa
                        // ini. Warna adaptif tema -- 0xFF0F172A hardcode
                        // nyaris hitam, tak kebaca di kartu ini pas mode gelap.
                        Flexible(
                          child: Text(
                            '${kos.occupiedRooms}/${kos.totalRooms} terisi (${kos.availableRooms} tersedia)',
                            textAlign: TextAlign.end,
                            style: TextStyle(fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              if (analyticsFuture != null) ...[
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Theme.of(context).dividerTheme.color ?? const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Aktivitas 8 Minggu Terakhir', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color)),
                      const SizedBox(height: 14),
                      FutureBuilder<List<WeeklyStat>>(
                        future: analyticsFuture,
                        builder: (context, snapshot) {
                          if (snapshot.connectionState == ConnectionState.waiting) {
                            return const SizedBox(height: 160, child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
                          }
                          if (snapshot.hasError || !snapshot.hasData) {
                            return const Text('Gagal memuat data analitik.', style: TextStyle(color: Colors.grey));
                          }
                          return WeeklyActivityChart(weeks: snapshot.data!);
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
              ],

              KosLocationMap(locationName: kos.location, latitude: kos.latitude, longitude: kos.longitude),
              const SizedBox(height: 20),

              if (kos.description.isNotEmpty) ...[
                Text('Deskripsi', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color)),
                const SizedBox(height: 8),
                // Warna adaptif tema di semua Text pada blok ini --
                // sebelumnya hardcode 0xFF0F172A/0xFF334155 (nyaris hitam),
                // tak kebaca sama sekali di kartu mode gelap.
                Text(kos.description, style: TextStyle(fontSize: 13.5, color: Theme.of(context).textTheme.bodyMedium?.color, height: 1.5)),
                const SizedBox(height: 20),
              ],

              Text('Fasilitas', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color)),
              const SizedBox(height: 8),
              kos.facilities.isEmpty
                  ? const Text('Belum ada fasilitas diisi', style: TextStyle(color: Colors.grey))
                  : Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: kos.facilities
                          .map((f) => Chip(label: Text(f.name, style: const TextStyle(fontSize: 12))))
                          .toList(),
                    ),
              const SizedBox(height: 20),

              Text('Aturan', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color)),
              const SizedBox(height: 8),
              kos.rules.isEmpty
                  ? const Text('Belum ada aturan diisi', style: TextStyle(color: Colors.grey))
                  : Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: kos.rules.map((r) => Chip(label: Text(r.name, style: const TextStyle(fontSize: 12)))).toList(),
                    ),
              const SizedBox(height: 20),

              Row(
                children: [
                  Text('Ulasan Penyewa', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Theme.of(context).textTheme.bodyLarge?.color)),
                  if (kos.averageReviewRating != null) ...[
                    const SizedBox(width: 8),
                    const Icon(Icons.star_rounded, color: Colors.amber, size: 16),
                    Text(' ${kos.averageReviewRating!.toStringAsFixed(1)} (${kos.reviewsCount})',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5, color: Theme.of(context).textTheme.bodyMedium?.color)),
                  ],
                ],
              ),
              const SizedBox(height: 8),
              if (kos.reviews.isEmpty)
                const Text('Belum ada ulasan dari penyewa.', style: TextStyle(color: Colors.grey))
              else
                ...kos.reviews.map((review) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Theme.of(context).dividerTheme.color ?? const Color(0xFFE2E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text(review.userName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13))),
                              ...List.generate(
                                5,
                                (i) => Icon(i < review.rating ? Icons.star_rounded : Icons.star_outline_rounded, color: Colors.amber, size: 13),
                              ),
                            ],
                          ),
                          if (review.comment != null && review.comment!.isNotEmpty) ...[
                            const SizedBox(height: 6),
                            // Warna adaptif tema -- lihat catatan di blok
                            // deskripsi di atas soal 0xFF334155 hardcode.
                            Text(review.comment!, style: TextStyle(fontSize: 12.5, color: Theme.of(context).textTheme.bodyMedium?.color)),
                          ],
                          if (review.ownerReply != null && review.ownerReply!.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: const Color(0xFF355DDB).withValues(alpha: 0.06),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: const Color(0xFF355DDB).withValues(alpha: 0.15)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Balasan Kamu', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF355DDB))),
                                  const SizedBox(height: 4),
                                  Text(review.ownerReply!, style: TextStyle(fontSize: 12.5, color: Theme.of(context).textTheme.bodyMedium?.color)),
                                ],
                              ),
                            ),
                          ],
                          const SizedBox(height: 8),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton.icon(
                              onPressed: () => _showReplyDialog(context, review),
                              icon: const Icon(Icons.reply_rounded, size: 15),
                              label: Text(review.ownerReply != null && review.ownerReply!.isNotEmpty ? 'Edit Balasan' : 'Balas Ulasan', style: const TextStyle(fontSize: 12)),
                              style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: Size.zero, foregroundColor: const Color(0xFF355DDB)),
                            ),
                          ),
                        ],
                      ),
                    )),
            ],
          ),
        );
      },
    );
  }

  Widget _statItem(IconData icon, String value, String label) {
    return Column(
      children: [
        Icon(icon, color: Colors.white, size: 20),
        const SizedBox(height: 6),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 2),
        Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 10.5)),
      ],
    );
  }
}

class _BookingTab extends StatelessWidget {
  final int kosId;
  const _BookingTab({required this.kosId});

  static const _statusLabel = {
    'pending': 'Menunggu',
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
    'completed': Color(0xFF355DDB),
  };

  Future<void> _respond(BuildContext context, Booking booking, String status) async {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final success = await provider.updateBookingStatus(booking.id, status);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Booking diperbarui.' : 'Gagal memperbarui booking -- mungkin kos sudah penuh.')),
    );
  }

  Future<void> _togglePayment(BuildContext context, Booking booking) async {
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final next = booking.paymentStatus == 'paid' ? 'unpaid' : 'paid';
    final success = await provider.updateBookingPaymentStatus(booking.id, next);
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Status pembayaran diperbarui.' : 'Gagal memperbarui status pembayaran.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<OwnerKosProvider>(context);
    final bookings = provider.bookings.where((b) => b.kosId == kosId).toList();
    final dateFormat = DateFormat('d MMM yyyy', 'id_ID');

    if (provider.isBookingsLoading && bookings.isEmpty) {
      return const _SkeletonCardList();
    }
    if (bookings.isEmpty) {
      return Center(
        child: Text('Belum ada booking masuk untuk kos ini.', style: TextStyle(color: Colors.grey[600])),
      );
    }

    return RefreshIndicator(
      onRefresh: () => provider.fetchBookings(),
      child: ListView.builder(
        padding: const EdgeInsets.all(20),
        itemCount: bookings.length,
        itemBuilder: (context, index) {
          final booking = bookings[index];
          final color = _statusColor[booking.status] ?? Colors.grey;
          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Theme.of(context).cardColor, borderRadius: BorderRadius.circular(16)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text('Mulai ${dateFormat.format(booking.startDate)} · ${booking.durationMonths} bulan',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                      child: Text(_statusLabel[booking.status] ?? booking.status,
                          style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold)),
                    ),
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
                            const Icon(Icons.person_outline_rounded, size: 13, color: Colors.grey),
                            const SizedBox(width: 4),
                            Flexible(
                              child: Text(booking.userName!, style: const TextStyle(fontSize: 12, color: Colors.grey), overflow: TextOverflow.ellipsis),
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
                if (booking.notes != null && booking.notes!.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text('Catatan: ${booking.notes}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                ],
                if (booking.status != 'rejected' && booking.status != 'cancelled') ...[
                  const SizedBox(height: 10),
                  InkWell(
                    borderRadius: BorderRadius.circular(20),
                    onTap: () => _togglePayment(context, booking),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: (booking.isPaid ? const Color(0xFF10B981) : const Color(0xFFF59E0B)).withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            booking.isPaid ? Icons.check_circle_rounded : Icons.hourglass_bottom_rounded,
                            size: 13,
                            color: booking.isPaid ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                          ),
                          const SizedBox(width: 5),
                          Text(
                            booking.isPaid ? 'Sudah Dibayar' : 'Tandai Sudah Dibayar',
                            style: TextStyle(
                              color: booking.isPaid ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
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
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _respond(context, booking, 'rejected'),
                          style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFEF4444)),
                          child: const Text('Tolak'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _respond(context, booking, 'confirmed'),
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF10B981)),
                          child: const Text('Konfirmasi', style: TextStyle(color: Colors.white)),
                        ),
                      ),
                    ],
                  ),
                ],
                if (booking.status == 'confirmed') ...[
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerRight,
                    child: OutlinedButton(
                      onPressed: () => _respond(context, booking, 'completed'),
                      child: const Text('Tandai Selesai'),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MatchesTab extends StatelessWidget {
  final Future<List<TenantMatch>>? matchesFuture;
  final String Function(num) formatRupiah;

  const _MatchesTab({required this.matchesFuture, required this.formatRupiah});

  @override
  Widget build(BuildContext context) {
    if (matchesFuture == null) {
      return const _SkeletonCardList();
    }
    return FutureBuilder<List<TenantMatch>>(
      future: matchesFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const _SkeletonCardList();
        }
        if (snapshot.hasError) {
          return Center(child: Text('Gagal memuat rekomendasi penyewa.', style: TextStyle(color: Colors.grey[600])));
        }
        final matches = snapshot.data ?? [];
        if (matches.isEmpty) {
          return Center(child: Text('Belum ada profil penyewa yang cocok.', style: TextStyle(color: Colors.grey[600])));
        }
        return ListView.builder(
          padding: const EdgeInsets.all(20),
          itemCount: matches.length,
          itemBuilder: (context, index) {
            final match = matches[index];
            return Container(
              margin: const EdgeInsets.only(bottom: 14),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: Theme.of(context).cardColor, borderRadius: BorderRadius.circular(16)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(match.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFF355DDB).withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text('${match.matchPercentage}% Cocok',
                            style: const TextStyle(color: Color(0xFF355DDB), fontSize: 11, fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${match.gender.toUpperCase()} · ${match.occupation} · ${match.preferredLocation}',
                    style: TextStyle(fontSize: 12.5, color: Colors.grey[600]),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Budget ${formatRupiah(match.budgetMin)} - ${formatRupiah(match.budgetMax)}',
                    style: const TextStyle(fontSize: 12.5, color: Color(0xFF355DDB), fontWeight: FontWeight.w600),
                  ),
                  if (match.preferredFacilities.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: match.preferredFacilities
                          .map((f) => Chip(
                                label: Text(f, style: const TextStyle(fontSize: 10)),
                                padding: EdgeInsets.zero,
                                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              ))
                          .toList(),
                    ),
                  ],
                ],
              ),
            );
          },
        );
      },
    );
  }
}

/// Skeleton loading buat tab "Info Kos" -- meniru bentuk kasar konten
/// sungguhan (gambar, kartu statistik, blok teks) selagi fetch pertama,
/// gantikan spinner polos di tengah layar kosong.
class _InfoTabSkeleton extends StatelessWidget {
  const _InfoTabSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: const [
        SkeletonBox(height: 180, borderRadius: BorderRadius.all(Radius.circular(16))),
        SizedBox(height: 20),
        SkeletonBox(height: 64, borderRadius: BorderRadius.all(Radius.circular(16))),
        SizedBox(height: 20),
        SkeletonBox(height: 14, width: 120),
        SizedBox(height: 10),
        SkeletonBox(height: 13),
        SizedBox(height: 6),
        SkeletonBox(height: 13, width: 220),
      ],
    );
  }
}

/// Skeleton loading generik untuk daftar berbentuk kartu (dipakai tab
/// "Booking" & "Rekomendasi Penyewa") -- tiap kartu skeleton meniru bentuk
/// kasar kartu sungguhan (judul + badge + 2 baris keterangan).
class _SkeletonCardList extends StatelessWidget {
  const _SkeletonCardList();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: 3,
      itemBuilder: (context, index) => Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Theme.of(context).cardColor, borderRadius: BorderRadius.circular(16)),
        child: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SkeletonBox(height: 14, width: 160),
            SizedBox(height: 10),
            SkeletonBox(height: 12, width: 200),
            SizedBox(height: 6),
            SkeletonBox(height: 12, width: 140),
          ],
        ),
      ),
    );
  }
}
