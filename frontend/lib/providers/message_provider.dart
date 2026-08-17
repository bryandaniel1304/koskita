import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:image_picker/image_picker.dart';
import '../models/message.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';
import '../services/app_badge_service.dart';
import '../services/offline_queue_service.dart';
import '../services/reverb_service.dart';

/// Hasil percobaan kirim pesan -- dibedakan antara benar-benar terkirim,
/// masuk antrian offline (ditahan, akan otomatis dicoba lagi begitu
/// koneksi pulih), atau gagal permanen (mis. ditolak validasi server).
/// Layar chat butuh bedakan ini supaya bisa kasih pesan yang tepat ke
/// pengguna, bukan cuma "berhasil"/"gagal".
enum MessageSendResult { sent, queued, failed }

/// State & aksi untuk pesan langsung penyewa <-> pemilik. Pesan MASUK
/// diterima real-time lewat [ReverbService] (lihat situ untuk kenapa
/// koneksinya ditulis manual, bukan pakai plugin native) -- provider ini
/// cuma dengarkan streamnya, tidak tahu-menahu soal WebSocket itu sendiri.
/// AuthProvider yang bertanggung jawab connect/disconnect ReverbService
/// (mengikuti status login), persis pola AppShortcutsService/AppBadgeService.
class MessageProvider with ChangeNotifier {
  List<Conversation> _conversations = [];
  List<Message> _thread = [];
  int _unreadCount = 0;
  int _pendingQueueCount = 0;
  bool _isLoadingConversations = false;
  bool _isLoadingThread = false;
  String? _errorMessage;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;
  StreamSubscription<Map<String, dynamic>>? _realtimeSub;

  List<Conversation> get conversations => _conversations;
  List<Message> get thread => _thread;
  int get unreadCount => _unreadCount;
  /// Jumlah pesan yang masih menunggu di antrian offline (belum terkirim
  /// ke server) -- dipakai layar chat/percakapan buat kasih tahu pengguna
  /// ada pesan yang belum benar-benar sampai.
  int get pendingQueueCount => _pendingQueueCount;
  bool get isLoadingConversations => _isLoadingConversations;
  bool get isLoadingThread => _isLoadingThread;
  String? get errorMessage => _errorMessage;

  MessageProvider() {
    unawaited(_refreshPendingQueueCount());
    // Begitu koneksi pulih (dari kondisi offline), coba kirim ulang semua
    // pesan yang tertahan di antrian secara otomatis -- pengguna tidak
    // perlu ingat buka lagi & kirim manual satu-satu.
    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) {
      final hasConnection = results.any((r) => r != ConnectivityResult.none);
      if (hasConnection) unawaited(_flushOfflineQueue());
    });
    _realtimeSub = ReverbService.onMessage.listen(_handleIncomingRealtimeMessage);
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    _realtimeSub?.cancel();
    super.dispose();
  }

  /// Payload dari [ReverbService.onMessage] -- shape-nya mengikuti
  /// `MessageSent::broadcastWith()` di backend, BUKAN endpoint REST biasa
  /// (tidak ada `receiver_id`, karena channel ini memang cuma dikirim ke
  /// kita sendiri sebagai penerima).
  void _handleIncomingRealtimeMessage(Map<String, dynamic> data) {
    final senderId = data['sender_id'];
    if (senderId is! int) return;

    // Kalau thread pengirim ini lagi terbuka, tambahkan bubble-nya langsung
    // tanpa fetch ulang -- kalau tidak, cukup refresh badge & daftar
    // percakapan supaya urutan/last-message ter-update.
    if (_currentThreadPartnerId == senderId) {
      _thread = [
        ..._thread,
        Message(
          id: data['id'] is int ? data['id'] : 0,
          senderId: senderId,
          receiverId: 0, // Kita sendiri -- tidak dipakai untuk render bubble lawan bicara.
          kosId: data['kos_id'],
          body: data['body'] ?? '',
          photoUrl: data['photo_url'],
          createdAt: DateTime.tryParse(data['created_at'] ?? '') ?? DateTime.now(),
        ),
      ];
      notifyListeners();
    }
    unawaited(fetchUnreadCount());
    unawaited(fetchConversations());
  }

  Future<void> _refreshPendingQueueCount() async {
    _pendingQueueCount = await OfflineQueueService.pendingCount();
    notifyListeners();
  }

  Future<void> _flushOfflineQueue() async {
    final sentCount = await OfflineQueueService.flush();
    await _refreshPendingQueueCount();
    if (sentCount > 0) {
      // Segarkan daftar percakapan & thread yang lagi dibuka (kalau ada)
      // supaya pesan yang baru berhasil terkirim langsung kelihatan.
      unawaited(fetchConversations());
      if (_currentThreadPartnerId != null) {
        unawaited(fetchThread(_currentThreadPartnerId!));
      }
    }
  }

  Future<void> fetchConversations() async {
    _errorMessage = null;
    _isLoadingConversations = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/messages/conversations');
      final data = jsonDecode(response.body);
      final List<dynamic> list = data['conversations'] ?? [];
      _conversations = list.map((c) => Conversation.fromJson(c)).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      _isLoadingConversations = false;
      notifyListeners();
    }
  }

  Future<void> fetchUnreadCount() async {
    try {
      final response = await ApiService.get('/messages/unread-count');
      final data = jsonDecode(response.body);
      _unreadCount = data['unread_count'] ?? 0;
      // Badge angka di ikon aplikasi (home screen) -- disinkronkan di sini
      // supaya otomatis ikut tiap kali unread count berubah, tanpa perlu
      // panggilan terpisah di tiap layar yang memicu fetchUnreadCount().
      unawaited(AppBadgeService.setCount(_unreadCount));
      notifyListeners();
    } catch (_) {
      // Badge -- gagal diam-diam saja, tidak ganggu alur utama.
    }
  }

  /// Partner ID dari thread yang lagi ditampilkan -- dipakai [fetchThread]
  /// buat pastikan hasil fetch yang telat datang (mis. pengguna sudah
  /// pindah ke percakapan lain) tidak menimpa thread yang salah.
  int? _currentThreadPartnerId;

  Future<void> fetchThread(int partnerId) async {
    _errorMessage = null;
    _isLoadingThread = true;
    // Kosongkan dulu thread lama SEBELUM fetch -- tanpa ini, pindah dari
    // percakapan A ke B akan sekilas menampilkan pesan-pesan A (stale)
    // sampai fetch B selesai, karena `thread` cuma diganti setelah await.
    if (_currentThreadPartnerId != partnerId) {
      _thread = [];
    }
    _currentThreadPartnerId = partnerId;
    notifyListeners();
    try {
      final response = await ApiService.get('/messages/thread/$partnerId');
      final data = jsonDecode(response.body);
      final List<dynamic> list = data['messages'] ?? [];
      // Pengguna mungkin sudah pindah ke thread lain sebelum request ini
      // selesai -- kalau begitu, jangan timpa thread yang sedang dilihat
      // sekarang dengan hasil yang sudah basi.
      if (_currentThreadPartnerId != partnerId) return;
      _thread = list.map((m) => Message.fromJson(m)).toList();
      // Buka thread otomatis tandai pesan masuk sudah dibaca di server --
      // sinkronkan badge lokal juga tanpa nunggu fetch ulang terpisah.
      unawaited(fetchUnreadCount());
    } on ApiException catch (e) {
      if (_currentThreadPartnerId != partnerId) return;
      _errorMessage = e.message;
    } catch (_) {
      if (_currentThreadPartnerId != partnerId) return;
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      if (_currentThreadPartnerId == partnerId) {
        _isLoadingThread = false;
        notifyListeners();
      }
    }
  }

  /// [photo] opsional -- kalau diisi, pesan dikirim sebagai multipart
  /// (boleh tanpa teks sama sekali, mirip WhatsApp kirim foto polos).
  /// CATATAN: pesan berfoto SENGAJA tidak ikut antrian offline seperti
  /// pesan teks biasa -- OfflineQueueService cuma nyimpan field teks di
  /// SharedPreferences, bukan file gambar, jadi kalau gagal karena
  /// koneksi ya langsung dianggap gagal (bukan queued), tidak menjanjikan
  /// "akan terkirim otomatis nanti" yang sebenarnya tidak bisa dipenuhi.
  Future<MessageSendResult> sendMessage({required int receiverId, String body = '', int? kosId, XFile? photo}) async {
    try {
      if (photo != null) {
        await ApiService.multipart(
          'POST',
          '/messages',
          {
            'receiver_id': receiverId.toString(),
            if (body.isNotEmpty) 'body': body,
            if (kosId != null) 'kos_id': kosId.toString(),
          },
          photos: [photo],
          fileFieldName: 'photo',
        );
      } else {
        await ApiService.post('/messages', {
          'receiver_id': receiverId,
          'body': body,
          if (kosId != null) 'kos_id': kosId,
        });
      }
      await fetchThread(receiverId);
      return MessageSendResult.sent;
    } on ApiException catch (e) {
      if (photo == null && (e.type == ApiErrorType.network || e.type == ApiErrorType.timeout)) {
        // Tidak ada koneksi -- tahan dulu di antrian lokal, bukan langsung
        // dianggap gagal, supaya pengguna tidak perlu ketik ulang manual
        // begitu koneksi pulih (lihat _flushOfflineQueue).
        await OfflineQueueService.enqueueMessage(receiverId: receiverId, body: body, kosId: kosId);
        await _refreshPendingQueueCount();
        return MessageSendResult.queued;
      }
      return MessageSendResult.failed;
    } catch (_) {
      return MessageSendResult.failed;
    }
  }
}
