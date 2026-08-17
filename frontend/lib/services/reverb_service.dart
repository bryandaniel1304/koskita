import 'dart:async';
import 'dart:convert';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'api_service.dart';
import '../config/app_config.dart';

/// Client WebSocket protokol Pusher tulisan tangan (pure Dart, tanpa plugin
/// native) yang konek ke server Laravel Reverb -- dipakai supaya pesan
/// masuk muncul instan di mobile tanpa buka ulang layar, persis seperti
/// yang sudah jalan di web (lihat `web/messages/thread.blade.php`).
///
/// SENGAJA ditulis manual pakai `web_socket_channel` (paket Dart murni,
/// tanpa kode native Android/iOS) alih-alih plugin resmi Pusher
/// (`pusher_channels_flutter`) -- yang terakhir itu MethodChannel native
/// yang tidak bisa diverifikasi lewat `flutter analyze`/`flutter test` di
/// lingkungan ini (butuh perangkat/emulator sungguhan), dan gagal-diam-diam
/// kalau server Reverb tidak jalan cukup penting untuk dikontrol sendiri
/// alurnya di sini.
///
/// Kalau server Reverb (`php artisan reverb:start`) sedang tidak jalan,
/// koneksi gagal dan otomatis dicoba lagi tiap 5 detik -- chat tetap
/// berfungsi normal lewat fetch biasa (kirim pesan -> `fetchThread()`),
/// cuma pesan MASUK dari lawan bicara tidak otomatis muncul tanpa refresh.
class ReverbService {
  ReverbService._();

  static WebSocketChannel? _channel;
  static String? _socketId;
  static int? _currentUserId;
  static bool _manuallyDisconnected = true;
  static Timer? _reconnectTimer;
  static StreamSubscription? _channelSub;

  static final _messageController = StreamController<Map<String, dynamic>>.broadcast();

  /// Payload mentah dari event `message.sent` (lihat `MessageSent`
  /// broadcastWith() di backend) -- MessageProvider yang menerjemahkannya
  /// jadi model Message supaya ReverbService tidak perlu tahu apa-apa
  /// soal model chat.
  static Stream<Map<String, dynamic>> get onMessage => _messageController.stream;

  static Future<void> connect(int userId) async {
    if (_currentUserId == userId && _channel != null) return;
    disconnect();
    _manuallyDisconnected = false;
    _currentUserId = userId;
    await _attemptConnect();
  }

  static Future<void> _attemptConnect() async {
    if (_manuallyDisconnected || _currentUserId == null) return;
    try {
      final configResponse = await ApiService.get('/broadcasting/config');
      final config = jsonDecode(configResponse.body);
      final host = Uri.parse(AppConfig.webBaseUrl).host;
      final port = config['port'];
      final key = config['key'];
      final scheme = config['scheme'] == 'wss' ? 'wss' : 'ws';
      if (key == null || host.isEmpty) {
        _scheduleReconnect();
        return;
      }

      final uri = Uri.parse('$scheme://$host:$port/app/$key?protocol=7&client=flutter&version=1.0&flash=false');
      final channel = WebSocketChannel.connect(uri);
      _channel = channel;
      _channelSub = channel.stream.listen(
        _handleRawMessage,
        onDone: _handleDisconnect,
        onError: (_) => _handleDisconnect(),
        cancelOnError: true,
      );
    } catch (_) {
      _scheduleReconnect();
    }
  }

  static void _handleRawMessage(dynamic raw) {
    try {
      final decoded = jsonDecode(raw as String) as Map<String, dynamic>;
      final event = decoded['event'];
      final rawData = decoded['data'];
      // Protokol Pusher selalu kirim "data" sebagai STRING ter-JSON-encode,
      // bukan objek bersarang langsung -- harus di-decode sekali lagi.
      final data = rawData is String ? jsonDecode(rawData) : rawData;

      if (event == 'pusher:connection_established') {
        _socketId = data['socket_id'];
        unawaited(_subscribeToUserChannel());
      } else if (event == 'message.sent' && data is Map<String, dynamic>) {
        _messageController.add(data);
      }
      // Event lain (pusher:error, pusher_internal:subscription_succeeded,
      // dst) sengaja diabaikan -- tidak ada state di sisi client yang
      // perlu bereaksi ke situ.
    } catch (_) {
      // Payload tak terduga -- abaikan satu pesan ini saja, jangan sampai
      // memutus koneksi cuma karena satu event yang gagal di-parse.
    }
  }

  static Future<void> _subscribeToUserChannel() async {
    final socketId = _socketId;
    final userId = _currentUserId;
    if (socketId == null || userId == null || _channel == null) return;

    final channelName = 'private-App.Models.User.$userId';
    try {
      final authResponse = await ApiService.post('/broadcasting/auth', {
        'channel_name': channelName,
        'socket_id': socketId,
      });
      final authData = jsonDecode(authResponse.body);
      _channel?.sink.add(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {'channel': channelName, 'auth': authData['auth']},
      }));
    } catch (_) {
      // Gagal otorisasi (mis. token kedaluwarsa di tengah jalan) -- biarkan
      // saja, koneksi tetap idle tanpa channel privat sampai reconnect
      // berikutnya (dipicu ulang lewat AuthProvider.login()/refreshUser()).
    }
  }

  static void _handleDisconnect() {
    _channelSub?.cancel();
    _channelSub = null;
    _channel = null;
    _socketId = null;
    if (!_manuallyDisconnected) _scheduleReconnect();
  }

  static void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(const Duration(seconds: 5), () {
      if (!_manuallyDisconnected) unawaited(_attemptConnect());
    });
  }

  /// Dipanggil saat logout / sesi tidak valid lagi (401) -- lihat
  /// AuthProvider. Menghentikan percobaan reconnect otomatis juga, supaya
  /// tidak terus mencoba konek pakai channel privat akun yang sudah keluar.
  static void disconnect() {
    _manuallyDisconnected = true;
    _currentUserId = null;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    _channelSub?.cancel();
    _channelSub = null;
    _channel?.sink.close();
    _channel = null;
    _socketId = null;
  }
}
