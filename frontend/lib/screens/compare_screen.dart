import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../models/kos.dart';
import '../config/app_theme.dart';
import '../widgets/skeleton_box.dart';

/// Perbandingan sisi-berdampingan untuk 2-3 kos pilihan -- dipanggil dari
/// Favorit (pilih beberapa kos favorit, lalu bandingkan).
class CompareScreen extends StatelessWidget {
  final List<Kos> koses;

  const CompareScreen({super.key, required this.koses});

  String _formatPrice(int price) => 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text('Bandingkan ${koses.length} Kos')),
      // Kolom label dikunci di kiri, kolom-kolom kos digulir ke SAMPING
      // (bukan cuma vertikal) -- sebelumnya Row berisi semua kolom kos
      // langsung dijejer tanpa batas lebar, jadi meluber (RenderFlex
      // overflow, garis-garis kuning-hitam) begitu kos yang dibandingkan
      // lebih dari yang muat di layar. Pola "kolom beku + area geser
      // horizontal" ini yang bikin berapa pun banyaknya kos tetap lancar.
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildLabelColumn(context),
              const SizedBox(width: 10),
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: koses.map((kos) => Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: _buildKosColumn(context, kos),
                        )).toList(),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLabelColumn(BuildContext context) {
    const labels = ['Harga', 'Lokasi', 'Jarak Kampus', 'Tipe', 'Rating', 'Kamar Tersedia', 'Fasilitas'];
    return SizedBox(
      width: 96,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 152), // ruang kosong sejajar dengan foto+nama kolom kos
          ...labels.map((label) => Container(
                height: label == 'Fasilitas' ? 140 : 52,
                alignment: Alignment.topLeft,
                child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppTheme.muted)),
              )),
        ],
      ),
    );
  }

  Widget _buildKosColumn(BuildContext context, Kos kos) {
    return SizedBox(
      width: 150,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GestureDetector(
            onTap: () => context.push('/kos/${kos.id}'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: CachedNetworkImage(
                    imageUrl: kos.coverImage,
                    height: 100,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => const SkeletonBox(height: 100, width: double.infinity),
                    // Tanpa ini, gambar yang gagal dimuat (URL mati/rusak)
                    // menampilkan apa pun yang dibalas server -- pola yang
                    // sama sudah dipakai di semua layar lain (Beranda,
                    // Favorit) yang menampilkan foto kos.
                    errorWidget: (context, url, error) => Container(
                      height: 100,
                      width: double.infinity,
                      color: Colors.grey[300],
                      child: const Icon(Icons.image_not_supported_outlined, color: Colors.grey),
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                Text(kos.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5)),
              ],
            ),
          ),
          _cell(context, _formatPrice(kos.price), bold: true, color: AppTheme.primary),
          _cell(context, kos.location),
          _cell(context, '${kos.distanceToCampus} km'),
          _cell(context, kos.genderType.toUpperCase()),
          _cell(context, kos.averageReviewRating != null ? '${kos.averageReviewRating!.toStringAsFixed(1)} ★ (${kos.reviewsCount})' : 'Belum ada'),
          _cell(context, kos.availableRooms > 0 ? '${kos.availableRooms} kamar' : 'Penuh', color: kos.availableRooms > 0 ? AppTheme.success : AppTheme.danger, bold: true),
          Container(
            height: 140,
            alignment: Alignment.topLeft,
            child: kos.facilities.isEmpty
                ? const Text('-', style: TextStyle(fontSize: 11.5))
                : Wrap(
                    spacing: 4,
                    runSpacing: 4,
                    children: kos.facilities
                        .map((f) => Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                              decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(6)),
                              child: Text(f.name, style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: AppTheme.primary)),
                            ))
                        .toList(),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _cell(BuildContext context, String value, {bool bold = false, Color? color}) {
    return Container(
      height: 52,
      alignment: Alignment.topLeft,
      child: Text(
        value,
        style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w800 : FontWeight.w600, color: color ?? Theme.of(context).textTheme.bodyLarge?.color),
      ),
    );
  }
}
