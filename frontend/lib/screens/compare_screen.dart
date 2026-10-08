import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../models/kos.dart';
import '../config/app_theme.dart';
import '../widgets/skeleton_box.dart';
import '../utils/price_format.dart';

/// Perbandingan sisi-berdampingan untuk kos-kos pilihan -- dipanggil dari
/// Favorit dan hasil pencarian Beranda (pilih beberapa kos, lalu bandingkan).
class CompareScreen extends StatefulWidget {
  final List<Kos> koses;

  const CompareScreen({super.key, required this.koses});

  @override
  State<CompareScreen> createState() => _CompareScreenState();
}

/// Satu baris perbandingan: label kategori + isi sel per kos.
class _CompareRow {
  final String label;
  final Widget Function(BuildContext context, Kos kos) cell;

  const _CompareRow(this.label, this.cell);
}

class _CompareScreenState extends State<CompareScreen> {
  static const double _labelWidth = 96;
  static const double _columnWidth = 150;

  // Tabel disusun PER BARIS (label + semua sel kos dalam satu Row), bukan
  // per kolom. Sebelumnya kolom label dan kolom-kolom kos dibangun terpisah
  // dengan tinggi baris yang ditebak (ruang header 152px, sel 52px,
  // fasilitas 140px), sehingga isi tergeser dari labelnya begitu tinggi
  // aslinya beda -- nama kos 1 vs 2 baris, ukuran huruf HP diperbesar, atau
  // fasilitas yang banyak. Dalam satu Row, label dan sel otomatis setinggi
  // sel tertinggi, jadi selalu sejajar.
  //
  // Label tetap dikunci di kiri; tiap baris punya area geser horizontal
  // sendiri yang posisinya disinkronkan, sehingga semua baris bergeser
  // bersama seperti satu tabel.
  late final List<_CompareRow> _rows = [
    _CompareRow('', _header),
    _CompareRow('Harga', (context, kos) => _text(context, _formatPrice(kos.price), bold: true, color: AppTheme.primary)),
    _CompareRow('Lokasi', (context, kos) => _text(context, kos.location)),
    _CompareRow('Jarak Kampus', (context, kos) => _text(context, '${kos.distanceToCampus} km')),
    _CompareRow('Tipe', (context, kos) => _text(context, kos.genderType.toUpperCase())),
    _CompareRow(
      'Rating',
      (context, kos) => _text(
        context,
        kos.averageReviewRating != null ? '${kos.averageReviewRating!.toStringAsFixed(1)} ★ (${kos.reviewsCount})' : 'Belum ada',
      ),
    ),
    _CompareRow(
      'Kamar Tersedia',
      (context, kos) => _text(
        context,
        kos.availableRooms > 0 ? '${kos.availableRooms} kamar' : 'Penuh',
        color: kos.availableRooms > 0 ? AppTheme.success : AppTheme.danger,
        bold: true,
      ),
    ),
    _CompareRow('Fasilitas', _facilities),
  ];

  late final List<ScrollController> _controllers = List.generate(_rows.length, (_) => ScrollController());
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    for (final controller in _controllers) {
      controller.addListener(() => _syncFrom(controller));
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers) {
      controller.dispose();
    }
    super.dispose();
  }

  /// Samakan posisi geser semua baris dengan baris yang sedang digeser.
  void _syncFrom(ScrollController source) {
    if (_syncing || !source.hasClients) return;
    _syncing = true;
    for (final controller in _controllers) {
      if (!identical(controller, source) && controller.hasClients && controller.offset != source.offset) {
        controller.jumpTo(source.offset.clamp(0.0, controller.position.maxScrollExtent));
      }
    }
    _syncing = false;
  }

  String _formatPrice(int price) => '${formatKosPrice(price)}/bln';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text('Bandingkan ${widget.koses.length} Kos')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var i = 0; i < _rows.length; i++) _buildRow(context, _rows[i], _controllers[i], isLast: i == _rows.length - 1),
          ],
        ),
      ),
    );
  }

  Widget _buildRow(BuildContext context, _CompareRow row, ScrollController controller, {required bool isLast}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: isLast ? null : Border(bottom: BorderSide(color: Theme.of(context).dividerColor.withValues(alpha: 0.4))),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: _labelWidth,
            child: Text(row.label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppTheme.muted)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: SingleChildScrollView(
              controller: controller,
              scrollDirection: Axis.horizontal,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: widget.koses
                    .map((kos) => Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: SizedBox(width: _columnWidth, child: row.cell(context, kos)),
                        ))
                    .toList(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header(BuildContext context, Kos kos) {
    return GestureDetector(
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
    );
  }

  Widget _facilities(BuildContext context, Kos kos) {
    if (kos.facilities.isEmpty) {
      return const Text('-', style: TextStyle(fontSize: 11.5));
    }
    return Wrap(
      spacing: 4,
      runSpacing: 4,
      children: kos.facilities
          .map((f) => Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(6)),
                child: Text(f.name, style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: AppTheme.primary)),
              ))
          .toList(),
    );
  }

  Widget _text(BuildContext context, String value, {bool bold = false, Color? color}) {
    return Text(
      value,
      style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w800 : FontWeight.w600, color: color ?? Theme.of(context).textTheme.bodyLarge?.color),
    );
  }
}
