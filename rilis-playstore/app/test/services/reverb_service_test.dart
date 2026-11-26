import 'package:flutter_test/flutter_test.dart';
import 'package:frontend/services/reverb_service.dart';

void main() {
  group('ReverbService.socketUri', () {
    test('Reverb: no host from the server, so the API host is used', () {
      final uri = ReverbService.socketUri(
        {'key': 'reverb-key', 'port': 8080, 'scheme': 'ws'},
        '192.168.1.5',
      );

      expect(uri.toString(),
          'ws://192.168.1.5:8080/app/reverb-key?protocol=7&client=flutter&version=1.0&flash=false');
    });

    test('Pusher: the host sent by the server wins over the API host', () {
      final uri = ReverbService.socketUri(
        {'key': 'pusher-key', 'host': 'ws-ap1.pusher.com', 'port': 443, 'scheme': 'wss'},
        'koskita.inovasidigital-si.id',
      );

      expect(uri!.scheme, 'wss');
      expect(uri.host, 'ws-ap1.pusher.com');
      expect(uri.port, 443);
      expect(uri.path, '/app/pusher-key');
    });

    test('empty server host falls back to the API host', () {
      final uri = ReverbService.socketUri(
        {'key': 'k', 'host': '', 'port': 443, 'scheme': 'wss'},
        'koskita.inovasidigital-si.id',
      );

      expect(uri!.host, 'koskita.inovasidigital-si.id');
    });

    test('missing key or host means no connection attempt', () {
      expect(ReverbService.socketUri({'port': 443, 'scheme': 'wss'}, 'example.com'), isNull);
      expect(ReverbService.socketUri({'key': 'k', 'port': 443, 'scheme': 'wss'}, ''), isNull);
    });
  });
}
