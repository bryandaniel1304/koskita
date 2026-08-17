import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/kos.dart';

void main() {
  group('Kos.fromJson', () {
    test('parses a full kos payload correctly', () {
      final json = {
        'id': 1,
        'name': 'Kos Melati',
        'price': 1500000,
        'gender_type': 'putri',
        'location': 'Karawaci',
        'latitude': -6.2088,
        'longitude': 106.6003,
        'distance_to_campus': 1.2,
        'description': 'Kos nyaman dekat kampus',
        'image_url': 'https://example.com/img.jpg',
        'cover_image': 'https://example.com/cover.jpg',
        'owner': {'id': 5, 'name': 'Budi'},
        'average_review_rating': 4.5,
        'reviews_count': 2,
        'reviews': [],
        'facilities': [
          {'id': 1, 'name': 'AC'},
        ],
        'rules': [],
        'images': [],
      };

      final kos = Kos.fromJson(json);

      expect(kos.id, 1);
      expect(kos.name, 'Kos Melati');
      expect(kos.price, 1500000);
      expect(kos.genderType, 'putri');
      expect(kos.latitude, -6.2088);
      expect(kos.longitude, 106.6003);
      expect(kos.ownerName, 'Budi');
      expect(kos.averageReviewRating, 4.5);
      expect(kos.reviewsCount, 2);
      expect(kos.facilities.length, 1);
      expect(kos.facilities.first.name, 'AC');
    });

    test('fills in safe defaults when fields are missing', () {
      final kos = Kos.fromJson({});

      expect(kos.id, 0);
      expect(kos.name, '');
      expect(kos.price, 0);
      expect(kos.latitude, isNull);
      expect(kos.longitude, isNull);
      expect(kos.ownerName, isNull);
      expect(kos.averageReviewRating, isNull);
      expect(kos.reviewsCount, 0);
      expect(kos.facilities, isEmpty);
    });

    test('galleryUrls falls back to coverImage when no images uploaded', () {
      final kos = Kos.fromJson({
        'id': 1,
        'cover_image': 'https://example.com/cover.jpg',
        'images': [],
      });

      expect(kos.galleryUrls, ['https://example.com/cover.jpg']);
    });
  });
}
