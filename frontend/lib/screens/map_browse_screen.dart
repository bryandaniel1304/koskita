import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:share_plus/share_plus.dart';
import '../providers/kos_provider.dart';
import '../models/kos.dart';
import '../config/app_theme.dart';

/// Peta sebaran semua kos -- SENGAJA tidak pakai lokasi GPS pengguna
/// ("cari di sekitar saya") supaya tidak perlu izin lokasi native
/// (geolocator + perubahan AndroidManifest/Info.plist) yang menambah
/// risiko build H-mendekati sidang. Titik tengah tiap area (Karawaci/BSD/
/// Serpong) sudah cukup untuk menampilkan sebaran kos secara visual.
class MapBrowseScreen extends StatefulWidget {
  const MapBrowseScreen({super.key});

  @override
  State<MapBrowseScreen> createState() => _MapBrowseScreenState();
}

enum _MapFilter { semua, kamarKosong, terverifikasi }

class _MapBrowseScreenState extends State<MapBrowseScreen> {
  static const Map<String, LatLng> _areaCenters = {
    'karawaci': LatLng(-6.2088, 106.6003),
    'bsd': LatLng(-6.3021, 106.6527),
    'serpong': LatLng(-6.3208, 106.6714),
  };
  static const double _initialZoom = 12.5;

  final _mapController = MapController();
  Kos? _selectedKos;
  double _zoom = _initialZoom;
  _MapFilter _filter = _MapFilter.semua;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = Provider.of<KosProvider>(context, listen: false);
      if (provider.koses.isEmpty) provider.fetchKoses();
    });
  }

  /// Titik kos di peta -- pakai koordinat presisi kalau ada, kalau tidak
  /// disebar kecil & konsisten (berdasarkan id, bukan acak tiap render) di
  /// sekitar titik tengah areanya supaya marker tidak numpuk persis di satu
  /// titik yang sama.
  LatLng _pointFor(Kos kos) {
    if (kos.latitude != null && kos.longitude != null) {
      return LatLng(kos.latitude!, kos.longitude!);
    }
    final center = _areaCenters[kos.location.toLowerCase().trim()] ?? _areaCenters['karawaci']!;
    final jitterLat = ((kos.id * 37) % 200 - 100) / 10000;
    final jitterLng = ((kos.id * 53) % 200 - 100) / 10000;
    return LatLng(center.latitude + jitterLat, center.longitude + jitterLng);
  }

  List<Kos> _applyFilter(List<Kos> koses) {
    switch (_filter) {
      case _MapFilter.kamarKosong:
        return koses.where((k) => k.availableRooms > 0).toList();
      case _MapFilter.terverifikasi:
        return koses.where((k) => k.verifiedAt != null).toList();
      case _MapFilter.semua:
        return koses;
    }
  }

  /// Warna pin dibedakan per tipe kos -- putra/putri/campur -- supaya
  /// sekali lihat peta, calon penyewa langsung tahu sebaran kos yang
  /// relevan buat gendernya tanpa harus tap satu-satu. Lihat _buildLegend.
  Color _genderColor(String genderType) {
    switch (genderType.toLowerCase().trim()) {
      case 'putra':
        return const Color(0xFF3B82F6);
      case 'putri':
        return const Color(0xFFF43F5E);
      default:
        return const Color(0xFFA855F7);
    }
  }

  /// Dengan 100+ kos, banyak yang koordinatnya cuma berjarak ratusan meter
  /// (1 kampus/kompleks yang sama) -- kalau semua digambar sebagai pin
  /// individual di zoom kota, jadinya numpuk 1 gerombolan tak terbaca.
  /// Solusi ringan tanpa nambah dependency baru: grid-cluster manual yang
  /// ukurannya menyesuaikan level zoom saat ini (grid makin kecil kalau
  /// user zoom in, jadi cluster otomatis pecah jadi pin individual).
  List<_MapCluster> _buildClusters(List<Kos> koses) {
    // Ukuran grid (derajat) mengecil seiring zoom bertambah -- di zoom 12
    // (lihat sekota) grid ~0.05 derajat (~5.5km, cukup gabungin klaster
    // Karawaci/UPH/Binong yang padat jadi beberapa badge saja), di zoom 16
    // (level jalan) grid ~0.003 derajat (~330m) sehingga pin yang jaraknya
    // wajar untuk dibedakan pengguna tidak lagi digabung. Konstanta dasar
    // sengaja besar -- ukuran ikon pin (36-44px) jauh lebih lebar daripada
    // jarak dalam derajat di zoom rendah, jadi grid harus lebih longgar
    // daripada sekadar "jarak asli di lapangan" supaya benar-benar
    // kebaca terpisah di layar.
    final gridSize = 0.05 * math.pow(2, 12 - _zoom).clamp(0.003, 0.15);

    final buckets = <String, List<Kos>>{};
    for (final kos in koses) {
      final point = _pointFor(kos);
      final key = '${(point.latitude / gridSize).round()}_${(point.longitude / gridSize).round()}';
      buckets.putIfAbsent(key, () => []).add(kos);
    }

    return buckets.values.map((group) {
      final points = group.map(_pointFor).toList();
      final avgLat = points.map((p) => p.latitude).reduce((a, b) => a + b) / points.length;
      final avgLng = points.map((p) => p.longitude).reduce((a, b) => a + b) / points.length;
      return _MapCluster(center: LatLng(avgLat, avgLng), koses: group);
    }).toList();
  }

  String _formatPrice(int price) => price >= 1000000 ? 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln' : 'Rp $price/bln';

  void _shareKos(Kos kos) {
    Share.share(
      'Lihat kos ini di KosKita: ${kos.name} -- ${kos.location}, mulai ${_formatPrice(kos.price)}/bulan.\n'
      'https://koskita.example.com/kos/${kos.id}',
    );
  }

  void _recenter() {
    setState(() => _zoom = _initialZoom);
    _mapController.move(_areaCenters['karawaci']!, _initialZoom);
  }

  void _focusOn(Kos kos) {
    setState(() => _selectedKos = kos);
    _mapController.move(_pointFor(kos), math.max(_zoom, 15));
  }

  void _openKosList(List<Kos> koses) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) => _KosListSheet(
        koses: koses,
        onSelect: (kos) {
          Navigator.of(sheetContext).pop();
          _focusOn(kos);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<KosProvider>(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final filtered = _applyFilter(provider.koses);
    final clusters = _buildClusters(filtered);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Peta Kos')),
      body: provider.isLoading && provider.koses.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : Stack(
              children: [
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: _areaCenters['karawaci']!,
                    initialZoom: _zoom,
                    onTap: (_, __) => setState(() => _selectedKos = null),
                    onPositionChanged: (position, hasGesture) {
                      final zoom = position.zoom;
                      if ((zoom - _zoom).abs() > 0.15) {
                        setState(() => _zoom = zoom);
                      }
                    },
                  ),
                  children: [
                    // Gaya peta ikut mode tema aplikasi -- gelap & moody pas
                    // dark mode (senada dengan tampilan peta app modern),
                    // terang & bersih pas light mode. Tile CartoDB dipakai
                    // (bukan cuma OSM standar) karena punya varian dark_all
                    // siap pakai tanpa API key.
                    TileLayer(
                      urlTemplate: isDark
                          ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                          : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                      subdomains: const ['a', 'b', 'c', 'd'],
                      userAgentPackageName: 'com.koskita.frontend',
                    ),
                    RichAttributionWidget(
                      alignment: AttributionAlignment.bottomLeft,
                      attributions: [
                        TextSourceAttribution('OpenStreetMap contributors', onTap: () {}),
                        const TextSourceAttribution('CARTO'),
                      ],
                    ),
                    MarkerLayer(
                      markers: clusters.map((cluster) {
                        if (cluster.koses.length == 1) {
                          final kos = cluster.koses.first;
                          final selected = _selectedKos?.id == kos.id;
                          final color = _genderColor(kos.genderType);
                          return Marker(
                            point: cluster.center,
                            width: selected ? 46 : 34,
                            height: selected ? 46 : 34,
                            child: GestureDetector(
                              onTap: () => setState(() => _selectedKos = kos),
                              child: selected
                                  ? Icon(Icons.location_on_rounded, color: color, size: 46)
                                  : Container(
                                      decoration: BoxDecoration(
                                        shape: BoxShape.circle,
                                        color: color,
                                        border: Border.all(color: Theme.of(context).cardColor, width: 2.5),
                                        boxShadow: AppTheme.softShadow(opacity: 0.25),
                                      ),
                                      alignment: Alignment.center,
                                      child: const Icon(Icons.home_rounded, color: Colors.white, size: 15),
                                    ),
                            ),
                          );
                        }

                        // Cluster berisi >1 kos -- tampilkan badge jumlah,
                        // tap buat zoom in ke area itu (otomatis memecah
                        // cluster jadi pin individual di zoom berikutnya).
                        return Marker(
                          point: cluster.center,
                          width: 44,
                          height: 44,
                          child: GestureDetector(
                            onTap: () {
                              setState(() => _selectedKos = null);
                              _mapController.move(cluster.center, _zoom + 2);
                            },
                            child: Container(
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                gradient: AppTheme.primaryGradient,
                                border: Border.all(color: Theme.of(context).cardColor, width: 2.5),
                                boxShadow: AppTheme.softShadow(opacity: 0.25),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                '${cluster.koses.length}',
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14),
                              ),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ],
                ),
                Positioned(
                  top: 12,
                  left: 12,
                  right: 66,
                  child: SizedBox(
                    height: 36,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: [
                        _FilterChip(label: 'Semua', selected: _filter == _MapFilter.semua, onTap: () => setState(() => _filter = _MapFilter.semua)),
                        const SizedBox(width: 8),
                        _FilterChip(label: 'Kamar Kosong', selected: _filter == _MapFilter.kamarKosong, onTap: () => setState(() => _filter = _MapFilter.kamarKosong)),
                        const SizedBox(width: 8),
                        _FilterChip(label: 'Terverifikasi', selected: _filter == _MapFilter.terverifikasi, onTap: () => setState(() => _filter = _MapFilter.terverifikasi)),
                      ],
                    ),
                  ),
                ),
                Positioned(
                  top: 12,
                  right: 12,
                  child: _RoundIconButton(icon: Icons.my_location_rounded, tooltip: 'Kembali ke tengah peta', onTap: _recenter),
                ),
                Positioned(
                  top: 56,
                  left: 12,
                  child: _buildLegend(context),
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 16,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_selectedKos != null) ...[
                        _KosPreviewCard(
                          kos: _selectedKos!,
                          formatPrice: _formatPrice,
                          onShare: () => _shareKos(_selectedKos!),
                          onOpen: () => context.push('/kos/${_selectedKos!.id}'),
                        ),
                        const SizedBox(height: 10),
                      ],
                      _ListButton(count: filtered.length, onTap: () => _openKosList(filtered)),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildLegend(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(12),
        boxShadow: AppTheme.softShadow(opacity: 0.12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          _legendRow(context, _genderColor('putra'), 'Putra'),
          const SizedBox(height: 4),
          _legendRow(context, _genderColor('putri'), 'Putri'),
          const SizedBox(height: 4),
          _legendRow(context, _genderColor('campur'), 'Campur'),
        ],
      ),
    );
  }

  Widget _legendRow(BuildContext context, Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 6),
        Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 11)),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppTheme.primary : Theme.of(context).cardColor,
      borderRadius: BorderRadius.circular(20),
      elevation: 0,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            boxShadow: AppTheme.softShadow(opacity: 0.1),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: selected ? Colors.white : Theme.of(context).textTheme.bodyMedium?.color,
            ),
          ),
        ),
      ),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  const _RoundIconButton({required this.icon, required this.tooltip, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).cardColor,
      shape: const CircleBorder(),
      elevation: 0,
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(shape: BoxShape.circle, boxShadow: AppTheme.softShadow(opacity: 0.12)),
          alignment: Alignment.center,
          child: Icon(icon, color: AppTheme.primary, size: 18),
        ),
      ),
    );
  }
}

class _ListButton extends StatelessWidget {
  final int count;
  final VoidCallback onTap;

  const _ListButton({required this.count, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppTheme.primary,
      borderRadius: BorderRadius.circular(26),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(26),
        child: Container(
          height: 48,
          padding: const EdgeInsets.symmetric(horizontal: 20),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(26), boxShadow: AppTheme.softShadow(tint: AppTheme.primary, opacity: 0.28)),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.list_rounded, color: Colors.white, size: 18),
              const SizedBox(width: 8),
              Text('Lihat Daftar Kos ($count)', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5)),
            ],
          ),
        ),
      ),
    );
  }
}

class _KosPreviewCard extends StatelessWidget {
  final Kos kos;
  final String Function(int) formatPrice;
  final VoidCallback onShare;
  final VoidCallback onOpen;

  const _KosPreviewCard({required this.kos, required this.formatPrice, required this.onShare, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(18),
        boxShadow: AppTheme.softShadow(opacity: 0.18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              GestureDetector(
                onTap: onOpen,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: CachedNetworkImage(imageUrl: kos.coverImage, width: 64, height: 64, fit: BoxFit.cover),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: GestureDetector(
                  onTap: onOpen,
                  behavior: HitTestBehavior.opaque,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(kos.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                          ),
                          if (kos.verifiedAt != null) ...[
                            const SizedBox(width: 4),
                            const Icon(Icons.verified_rounded, size: 15, color: AppTheme.primary),
                          ],
                        ],
                      ),
                      const SizedBox(height: 3),
                      Text(kos.location, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Text(formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5, color: AppTheme.primary)),
                          if ((kos.averageReviewRating ?? 0) > 0) ...[
                            const SizedBox(width: 8),
                            const Icon(Icons.star_rounded, size: 13, color: Color(0xFFF59E0B)),
                            const SizedBox(width: 2),
                            Text(kos.averageReviewRating!.toStringAsFixed(1), style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onOpen,
                  icon: const Icon(Icons.chevron_right_rounded, size: 18),
                  label: const Text('Lihat Detail', style: TextStyle(fontSize: 12.5)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppTheme.primary,
                    side: const BorderSide(color: AppTheme.primaryLight),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _RoundIconButton(icon: Icons.share_rounded, tooltip: 'Bagikan kos ini', onTap: onShare),
            ],
          ),
        ],
      ),
    );
  }
}

class _KosListSheet extends StatelessWidget {
  final List<Kos> koses;
  final ValueChanged<Kos> onSelect;

  const _KosListSheet({required this.koses, required this.onSelect});

  String _formatPrice(int price) => price >= 1000000 ? 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln' : 'Rp $price/bln';

  @override
  Widget build(BuildContext context) {
    return FractionallySizedBox(
      heightFactor: 0.72,
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(4)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
              child: Row(
                children: [
                  Text('${koses.length} Kos di Peta', style: Theme.of(context).textTheme.titleMedium),
                ],
              ),
            ),
            Expanded(
              child: koses.isEmpty
                  ? Center(child: Text('Tidak ada kos yang cocok dengan filter ini.', style: Theme.of(context).textTheme.bodyMedium))
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      itemCount: koses.length,
                      separatorBuilder: (_, __) => const Divider(height: 18),
                      itemBuilder: (context, index) {
                        final kos = koses[index];
                        return GestureDetector(
                          onTap: () => onSelect(kos),
                          behavior: HitTestBehavior.opaque,
                          child: Row(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: CachedNetworkImage(imageUrl: kos.coverImage, width: 52, height: 52, fit: BoxFit.cover),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(kos.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                                    const SizedBox(height: 2),
                                    Text(kos.location, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: AppTheme.primary)),
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MapCluster {
  final LatLng center;
  final List<Kos> koses;

  const _MapCluster({required this.center, required this.koses});
}
