import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:http/http.dart' as http;
import '../config/app_theme.dart';

/// Pengganti 2 kolom angka Latitude/Longitude manual -- pemilik kos dulu
/// harus buka Google Maps sendiri, tekan lama titik lokasi, lalu SALIN
/// PASTE 2 angka koordinat, yang ternyata kerasa ribet & rawan salah.
/// Sekarang cukup dua cara (bisa dipakai bergantian): ketik alamat lalu
/// pilih hasil pencarian (geocoding via Nominatim/OpenStreetMap -- gratis,
/// tanpa API key, konsisten dengan tile peta yang sudah dipakai di app
/// ini), ATAU langsung ketuk titik yang benar di peta buat naruh pin.
/// Tetap opsional -- kalau pin gak pernah disentuh, `onChanged` gak pernah
/// dipanggil dan kos tetap bisa disimpan tanpa koordinat presisi (fallback
/// ke titik tengah area, sama seperti perilaku lama).
class LocationPickerField extends StatefulWidget {
  final double? initialLatitude;
  final double? initialLongitude;
  final ValueChanged<LatLng?> onChanged;

  const LocationPickerField({
    super.key,
    this.initialLatitude,
    this.initialLongitude,
    required this.onChanged,
  });

  @override
  State<LocationPickerField> createState() => _LocationPickerFieldState();
}

class _LocationPickerFieldState extends State<LocationPickerField> {
  static const _defaultCenter = LatLng(-6.2088, 106.6003); // Karawaci

  final _mapController = MapController();
  final _searchController = TextEditingController();
  LatLng? _picked;
  bool _searching = false;
  String? _searchError;

  @override
  void initState() {
    super.initState();
    if (widget.initialLatitude != null && widget.initialLongitude != null) {
      _picked = LatLng(widget.initialLatitude!, widget.initialLongitude!);
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _searchAddress() async {
    final query = _searchController.text.trim();
    if (query.isEmpty) return;

    setState(() {
      _searching = true;
      _searchError = null;
    });

    try {
      // Dibatasi ke Indonesia (countrycodes=id) supaya hasil pencarian
      // alamat pendek (mis. "Lippo Karawaci") tidak nyasar ke negara lain.
      // limit=5 (bukan 1) SENGAJA -- nama tempat yang sama bisa ada di kota
      // berbeda (dites manual: "Universitas Pelita Harapan" hasil #1-nya
      // kampus di Medan, bukan yang di Karawaci/Tangerang!). Kalau langsung
      // ambil hasil pertama, pin bisa nyasar ke kota lain tanpa pemilik
      // sadar. Jadi kalau hasilnya lebih dari satu, user pilih sendiri
      // yang mana yang benar lewat daftar di bawah.
      final uri = Uri.https('nominatim.openstreetmap.org', '/search', {
        'q': query,
        'format': 'json',
        'limit': '5',
        'countrycodes': 'id',
      });
      final response = await http.get(
        uri,
        // Wajib diisi sesuai kebijakan penggunaan Nominatim -- request
        // tanpa User-Agent yang jelas bisa diblokir. CATATAN: sempat pakai
        // email placeholder "@example.com" di sini dan SEMUA request
        // langsung kena 403 "Access denied" -- filter anti-abuse Nominatim
        // rupanya menyaring domain placeholder seperti example.com. Format
        // tanpa email/domain begini sudah dites manual dan lolos (200 OK).
        headers: {'User-Agent': 'KosKitaApp/1.0 (Skripsi project, Android)'},
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode != 200) {
        throw Exception('status ${response.statusCode}');
      }
      final results = (jsonDecode(response.body) as List).cast<Map<String, dynamic>>();
      if (results.isEmpty) {
        setState(() => _searchError = 'Alamat tidak ditemukan, coba kata kunci lain.');
        return;
      }
      if (!mounted) return;

      final chosen = results.length == 1 ? results.first : await _pickResult(results);
      if (chosen == null) return; // user membatalkan pemilihan

      final lat = double.parse(chosen['lat'] as String);
      final lon = double.parse(chosen['lon'] as String);
      final point = LatLng(lat, lon);

      setState(() => _picked = point);
      widget.onChanged(point);
      _mapController.move(point, 16);
    } catch (_) {
      setState(() => _searchError = 'Gagal mencari alamat. Cek koneksi internet, atau ketuk peta langsung.');
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  /// Beberapa hasil ditemukan buat query yang sama -- tampilkan sebagai
  /// daftar (bottom sheet) supaya pemilik yang menentukan mana yang benar,
  /// bukan asal ambil hasil teratas yang bisa saja di kota lain.
  Future<Map<String, dynamic>?> _pickResult(List<Map<String, dynamic>> results) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Text('Ada beberapa alamat mirip, pilih yang benar:', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
            ),
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: results.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  final r = results[index];
                  return ListTile(
                    leading: const Icon(Icons.location_on_outlined, color: AppTheme.primary),
                    title: Text(r['display_name'] as String? ?? '', style: const TextStyle(fontSize: 13.5)),
                    onTap: () => Navigator.of(context).pop(r),
                  );
                },
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  void _clearPin() {
    setState(() {
      _picked = null;
      _searchError = null;
    });
    widget.onChanged(null);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _searchController,
                decoration: const InputDecoration(
                  hintText: 'Cari alamat, mis. "Jl. Palem Karawaci"',
                  prefixIcon: Icon(Icons.search_rounded, size: 20),
                ),
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _searchAddress(),
              ),
            ),
            const SizedBox(width: 8),
            SizedBox(
              height: 54,
              child: ElevatedButton(
                onPressed: _searching ? null : _searchAddress,
                style: ElevatedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 16)),
                child: _searching
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation(Colors.white)))
                    : const Text('Cari'),
              ),
            ),
          ],
        ),
        if (_searchError != null) ...[
          const SizedBox(height: 6),
          Text(_searchError!, style: const TextStyle(color: AppTheme.danger, fontSize: 12)),
        ],
        const SizedBox(height: 10),
        ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: SizedBox(
            height: 200,
            child: FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: _picked ?? _defaultCenter,
                initialZoom: _picked != null ? 16 : 12.5,
                onTap: (tapPosition, point) {
                  setState(() {
                    _picked = point;
                    _searchError = null;
                  });
                  widget.onChanged(point);
                },
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.koskita.frontend',
                ),
                if (_picked != null)
                  MarkerLayer(markers: [
                    Marker(
                      point: _picked!,
                      width: 40,
                      height: 40,
                      child: const Icon(Icons.location_on_rounded, color: AppTheme.danger, size: 40),
                    ),
                  ]),
              ],
            ),
          ),
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            Icon(_picked != null ? Icons.check_circle_outline : Icons.info_outline, size: 13, color: Colors.grey),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                _picked != null
                    ? 'Pin dipasang -- ketuk peta lagi buat geser, atau cari alamat lain.'
                    : 'Belum ada pin -- cari alamat di atas atau ketuk peta langsung. Boleh dilewati (opsional).',
                style: TextStyle(fontSize: 11.5, color: Colors.grey[600]),
              ),
            ),
            if (_picked != null)
              TextButton(
                onPressed: _clearPin,
                style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: Size.zero),
                child: const Text('Hapus pin', style: TextStyle(fontSize: 12)),
              ),
          ],
        ),
      ],
    );
  }
}
