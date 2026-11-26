import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'api_exception.dart';

/// Antrian pesan yang gagal terkirim karena tidak ada koneksi -- disimpan
/// LOKAL di HP (SharedPreferences), bukan hilang begitu saja/harus diketik
/// ulang manual. Begitu koneksi pulih (lihat listener `connectivity_plus`
/// di [MessageProvider]), antrian ini dicoba dikirim ulang otomatis.
class OfflineQueueService {
  static const _key = 'offline_message_queue_v1';

  static Future<List<Map<String, dynamic>>> _load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      if (raw == null) return [];
      final List<dynamic> data = jsonDecode(raw);
      return data.cast<Map<String, dynamic>>();
    } catch (_) {
      return [];
    }
  }

  static Future<void> _save(List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(items));
  }

  static Future<void> enqueueMessage({required int receiverId, required String body, int? kosId}) async {
    final items = await _load();
    items.add({
      'receiver_id': receiverId,
      'body': body,
      if (kosId != null) 'kos_id': kosId,
      // Cuma buat ditampilkan di UI (mis. "menunggu sejak..."), TIDAK
      // dikirim ke server -- lihat _flush() yang membuang field ini.
      'queued_at': DateTime.now().toIso8601String(),
    });
    await _save(items);
  }

  static Future<int> pendingCount() async => (await _load()).length;

  /// Pesan yang masih tertunda untuk satu lawan bicara tertentu -- dipakai
  /// layar chat supaya bisa menampilkan bubble "belum terkirim" untuk
  /// pesan yang masih di antrian, bukan cuma hilang dari tampilan.
  static Future<List<Map<String, dynamic>>> pendingFor(int receiverId) async {
    final items = await _load();
    return items.where((i) => i['receiver_id'] == receiverId).toList();
  }

  /// Coba kirim ulang semua pesan tertunda, SATU PER SATU secara urut
  /// (bukan paralel) supaya urutan percakapan di server tetap sesuai
  /// urutan pengiriman aslinya. Begitu ketemu satu yang masih gagal
  /// (mis. koneksi baru putus lagi di tengah proses), sisanya (termasuk
  /// yang gagal itu) dibiarkan di antrian buat dicoba lagi nanti --
  /// bukan dilewati begitu saja, supaya tidak ada pesan yang hilang atau
  /// terkirim tidak berurutan.
  static Future<int> flush() async {
    final items = await _load();
    if (items.isEmpty) return 0;
    var sentCount = 0;
    var stillFailing = false;
    final remaining = <Map<String, dynamic>>[];
    for (final item in items) {
      if (stillFailing) {
        remaining.add(item);
        continue;
      }
      try {
        await ApiService.post('/messages', {
          'receiver_id': item['receiver_id'],
          'body': item['body'],
          if (item['kos_id'] != null) 'kos_id': item['kos_id'],
        });
        sentCount++;
      } on ApiException catch (e) {
        if (e.type == ApiErrorType.network || e.type == ApiErrorType.timeout) {
          stillFailing = true;
          remaining.add(item);
        }
        // Selain masalah koneksi (mis. validasi ditolak server), buang saja
        // dari antrian -- diulang-ulang otomatis juga tidak akan berhasil.
      } catch (_) {
        stillFailing = true;
        remaining.add(item);
      }
    }
    await _save(remaining);
    return sentCount;
  }
}
