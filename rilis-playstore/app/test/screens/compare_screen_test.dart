import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/kos.dart';
import 'package:frontend/screens/compare_screen.dart';

void main() {
  // Dua kos dengan tinggi isi yang sengaja berbeda: nama panjang (2 baris)
  // + banyak fasilitas vs nama pendek + satu fasilitas -- kombinasi yang
  // dulu membuat sel bergeser dari label kategorinya.
  final koses = [
    Kos.fromJson({
      'id': 1,
      'name': 'Kost Exclusive Vila Serpong BSD Tangerang Dekat Kampus',
      'price': 1599000,
      'gender_type': 'campur',
      'location': 'Serpong',
      'distance_to_campus': 7.65,
      'available_rooms': 4,
      'facilities': [
        for (final name in ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry', 'Kasur', 'Lemari', 'Meja', 'CCTV'])
          {'id': name.hashCode, 'name': name},
      ],
    }),
    Kos.fromJson({
      'id': 2,
      'name': 'Kost Putri',
      'price': 1300000,
      'gender_type': 'putri',
      'location': 'Karawaci',
      'distance_to_campus': 2.5,
      'available_rooms': 0,
      'facilities': [
        {'id': 99, 'name': 'Bebas Banjir'},
      ],
    }),
  ];

  // Nilai tiap sel per kos, berurutan sama dengan [koses].
  const rows = {
    'Harga': ['Rp 1.6 jt/bln', 'Rp 1.3 jt/bln'],
    'Lokasi': ['Serpong', 'Karawaci'],
    'Jarak Kampus': ['7.65 km', '2.5 km'],
    'Tipe': ['CAMPUR', 'PUTRI'],
    'Kamar Tersedia': ['4 kamar', 'Penuh'],
    'Fasilitas': ['AC', 'Bebas Banjir'],
  };

  setUp(() {
    final binding = TestWidgetsFlutterBinding.ensureInitialized();
    binding.platformDispatcher.views.first.physicalSize = const Size(1080, 2400);
    binding.platformDispatcher.views.first.devicePixelRatio = 1.0;
    addTearDown(binding.platformDispatcher.views.first.resetPhysicalSize);
    addTearDown(binding.platformDispatcher.views.first.resetDevicePixelRatio);
  });

  Future<void> expectRowsAligned(WidgetTester tester) async {
    for (final entry in rows.entries) {
      final labelTop = tester.getTopLeft(find.text(entry.key)).dy;
      for (final value in entry.value) {
        // Fasilitas tampil sebagai chip berpadding -- yang harus sejajar
        // adalah tepi atas kumpulan chip-nya, bukan teks di dalam chip.
        final cell = entry.key == 'Fasilitas'
            ? find.ancestor(of: find.text(value).first, matching: find.byType(Wrap))
            : find.text(value).first;
        expect(
          tester.getTopLeft(cell).dy,
          labelTop,
          reason: '"$value" harus sejajar dengan label "${entry.key}"',
        );
      }
    }
    // Rating kedua kos sama-sama "Belum ada".
    final ratingTop = tester.getTopLeft(find.text('Rating')).dy;
    for (final element in find.text('Belum ada').evaluate()) {
      expect(tester.getTopLeft(find.byWidget(element.widget)).dy, ratingTop);
    }

    // Lepas layar & habiskan timer placeholder foto (skeleton/fade
    // CachedNetworkImage) supaya test tidak gagal karena timer tertunda.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(seconds: 2));
  }

  testWidgets('every value sits on the same line as its category label', (tester) async {
    await tester.pumpWidget(MaterialApp(home: CompareScreen(koses: koses)));
    await tester.pump();

    await expectRowsAligned(tester);
  });

  testWidgets('rows stay aligned with an enlarged phone font size', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: MediaQuery(
        data: const MediaQueryData(textScaler: TextScaler.linear(1.4)),
        child: CompareScreen(koses: koses),
      ),
    ));
    await tester.pump();

    await expectRowsAligned(tester);
  });
}
