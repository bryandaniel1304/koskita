import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

/// Peta lokasi kos pakai OpenStreetMap (gratis, tanpa API key/akun apapun --
/// beda dari Google Maps yang butuh billing account). Kalau kos belum
/// diisi koordinat presisi (latitude/longitude), fallback ke titik tengah
/// area umum (Karawaci/BSD/Serpong) supaya peta tetap tampil untuk data
/// lama yang belum ada koordinatnya.
class KosLocationMap extends StatelessWidget {
  final String locationName;
  final double? latitude;
  final double? longitude;

  const KosLocationMap({
    super.key,
    required this.locationName,
    this.latitude,
    this.longitude,
  });

  static const Map<String, LatLng> _areaCenters = {
    'karawaci': LatLng(-6.2088, 106.6003),
    'bsd': LatLng(-6.3021, 106.6527),
    'serpong': LatLng(-6.3208, 106.6714),
  };

  LatLng get _point {
    if (latitude != null && longitude != null) {
      return LatLng(latitude!, longitude!);
    }
    final key = locationName.toLowerCase().trim();
    return _areaCenters[key] ?? _areaCenters['karawaci']!;
  }

  bool get _isPrecise => latitude != null && longitude != null;

  @override
  Widget build(BuildContext context) {
    final point = _point;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: SizedBox(
            height: 180,
            child: FlutterMap(
              options: MapOptions(
                initialCenter: point,
                initialZoom: _isPrecise ? 16 : 13,
                interactionOptions: const InteractionOptions(flags: InteractiveFlag.pinchZoom | InteractiveFlag.drag),
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.koskita.frontend',
                ),
                MarkerLayer(
                  markers: [
                    Marker(
                      point: point,
                      width: 40,
                      height: 40,
                      child: const Icon(Icons.location_on_rounded, color: Color(0xFFF43F5E), size: 40),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            Icon(_isPrecise ? Icons.check_circle_outline : Icons.info_outline, size: 13, color: Colors.grey),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                _isPrecise ? 'Lokasi presisi dari pemilik kos' : 'Perkiraan area $locationName (lokasi presisi belum diisi pemilik)',
                style: TextStyle(fontSize: 11.5, color: Colors.grey[600]),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
