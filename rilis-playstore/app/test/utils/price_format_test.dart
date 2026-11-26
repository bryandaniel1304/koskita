import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/utils/price_format.dart';

void main() {
  test('millions use the short "jt" form', () {
    expect(formatKosPrice(1599000), 'Rp 1.6 jt');
    expect(formatKosPrice(1000000), 'Rp 1.0 jt');
  });

  test('prices under a million use "rb" instead of the raw number', () {
    expect(formatKosPrice(500000), 'Rp 500 rb');
    expect(formatKosPrice(650000), 'Rp 650 rb');
  });

  test('tiny values stay as-is', () {
    expect(formatKosPrice(0), 'Rp 0');
  });
}
