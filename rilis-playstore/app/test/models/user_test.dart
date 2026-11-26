import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/models/user.dart';

void main() {
  group('User.fromJson', () {
    test('marks user verified when email_verified_at is present', () {
      final user = User.fromJson({
        'id': 1,
        'name': 'Budi',
        'email': 'budi@example.com',
        'role': 'user',
        'email_verified_at': '2026-08-05T00:00:00.000000Z',
      });

      expect(user.isEmailVerified, isTrue);
    });

    test('marks user unverified when email_verified_at is null', () {
      final user = User.fromJson({
        'id': 1,
        'name': 'Budi',
        'email': 'budi@example.com',
        'role': 'user',
        'email_verified_at': null,
      });

      expect(user.isEmailVerified, isFalse);
    });

    test('defaults role to "user" when missing', () {
      final user = User.fromJson({'id': 1, 'name': 'Budi', 'email': 'budi@example.com'});

      expect(user.role, 'user');
    });
  });
}
