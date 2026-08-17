import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../models/kos.dart';
import '../models/kos_room_type.dart';
import '../models/facility.dart';
import '../models/rule.dart';
import '../models/booking.dart';
import '../models/tenant_match.dart';
import '../models/owner_kos_stats.dart';
import '../models/weekly_stat.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

/// State & aksi untuk akun Penyedia Kos: kelola listing kos miliknya sendiri,
/// booking yang masuk, dan rekomendasi penyewa yang paling cocok per kos.
class OwnerKosProvider with ChangeNotifier {
  List<Kos> _koses = [];
  List<Facility> _facilities = [];
  List<Rule> _rules = [];
  List<Booking> _bookings = [];
  bool _isLoading = false;
  bool _isBookingsLoading = false;
  String? _errorMessage;
  String? _bookingsErrorMessage;

  List<Kos> get koses => _koses;
  List<Facility> get facilities => _facilities;
  List<Rule> get rules => _rules;
  List<Booking> get bookings => _bookings;
  bool get isLoading => _isLoading;
  bool get isBookingsLoading => _isBookingsLoading;
  String? get errorMessage => _errorMessage;
  String? get bookingsErrorMessage => _bookingsErrorMessage;

  /// Jumlah booking berstatus "pending" lintas semua kos milik pemilik --
  /// dipakai buat badge notifikasi di bottom nav & ringkasan dashboard.
  int get pendingBookingsCount => _bookings.where((b) => b.status == 'pending').length;

  Future<void> fetchMeta() async {
    try {
      final response = await ApiService.get('/meta');
      final data = jsonDecode(response.body);
      _facilities = (data['facilities'] as List).map((f) => Facility.fromJson(f)).toList();
      _rules = (data['rules'] as List).map((r) => Rule.fromJson(r)).toList();
      notifyListeners();
    } on ApiException catch (_) {
      // Form tetap bisa dipakai tanpa checklist fasilitas/aturan kalau gagal.
    } catch (_) {
      // Idem.
    }
  }

  Future<void> fetchKoses() async {
    _errorMessage = null;
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/owner/koses');
      final List<dynamic> data = jsonDecode(response.body);
      _koses = data.map((k) => Kos.fromJson(k)).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (_) {
      // Jaring pengaman -- tanpa ini, exception non-ApiException bikin
      // layar "Kos Saya" nyangkut loading selamanya tanpa pesan error.
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchBookings() async {
    _bookingsErrorMessage = null;
    _isBookingsLoading = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/owner/bookings');
      final List<dynamic> data = jsonDecode(response.body);
      _bookings = data.map((b) => Booking.fromJson(b)).toList();
    } on ApiException catch (e) {
      _bookingsErrorMessage = e.message;
    } catch (_) {
      _bookingsErrorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      _isBookingsLoading = false;
      notifyListeners();
    }
  }

  Future<bool> updateBookingStatus(int id, String status, {String? note}) async {
    try {
      await ApiService.put('/owner/bookings/$id', {
        'status': status,
        if (note != null && note.isNotEmpty) 'admin_note': note,
      });
      await fetchBookings();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Tandai booking sudah/belum dibayar -- penanda MANUAL oleh pemilik
  /// (KosKita tidak memproses pembayaran/transaksi finansial apapun).
  Future<bool> updateBookingPaymentStatus(int id, String paymentStatus) async {
    try {
      await ApiService.put('/owner/bookings/$id/payment-status', {'payment_status': paymentStatus});
      await fetchBookings();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Kirim/perbarui balasan pemilik atas satu ulasan -- kirim string kosong
  /// untuk menghapus balasan yang sudah ada. Tidak mengubah state lokal
  /// sendiri; pemanggil bertanggung jawab refresh (mis. fetchKosDetail lagi)
  /// supaya balasan baru langsung terlihat.
  Future<bool> replyToReview(int reviewId, String reply) async {
    try {
      await ApiService.post('/owner/reviews/$reviewId/reply', {'reply': reply});
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Detail satu kos milik pemilik + statistik privatnya (views, favorit,
  /// rating) -- dipakai tab "Info Kos" di halaman detail.
  Future<(Kos, OwnerKosStats)> fetchKosDetail(int kosId) async {
    final response = await ApiService.get('/owner/koses/$kosId');
    final data = jsonDecode(response.body);
    return (Kos.fromJson(data['kos']), OwnerKosStats.fromJson(data['stats']));
  }

  Future<List<TenantMatch>> fetchMatches(int kosId) async {
    final response = await ApiService.get('/owner/koses/$kosId/matches');
    final data = jsonDecode(response.body);
    final List<dynamic> matches = data['matches'] ?? [];
    return matches.map((m) => TenantMatch.fromJson(m)).toList();
  }

  /// Aktivitas 8 minggu terakhir (booking masuk & ulasan baru) -- dipakai
  /// grafik analitik di tab "Info Kos".
  Future<List<WeeklyStat>> fetchAnalytics(int kosId) async {
    final response = await ApiService.get('/owner/koses/$kosId/analytics');
    final data = jsonDecode(response.body);
    final List<dynamic> weeks = data['weeks'] ?? [];
    return weeks.map((w) => WeeklyStat.fromJson(w)).toList();
  }

  Map<String, String> _kosFields({
    required String name,
    required int price,
    required String genderType,
    required String location,
    double? latitude,
    double? longitude,
    required double distanceToCampus,
    required int totalRooms,
    required String description,
    required List<int> facilityIds,
    required List<int> ruleIds,
  }) {
    final fields = {
      'name': name,
      'price': price.toString(),
      'gender_type': genderType,
      'location': location,
      'distance_to_campus': distanceToCampus.toString(),
      'total_rooms': totalRooms.toString(),
      'description': description,
      if (latitude != null) 'latitude': latitude.toString(),
      if (longitude != null) 'longitude': longitude.toString(),
    };
    for (var i = 0; i < facilityIds.length; i++) {
      fields['facilities[$i]'] = facilityIds[i].toString();
    }
    for (var i = 0; i < ruleIds.length; i++) {
      fields['rules[$i]'] = ruleIds[i].toString();
    }
    return fields;
  }

  Future<String?> createKos({
    required String name,
    required int price,
    required String genderType,
    required String location,
    double? latitude,
    double? longitude,
    required double distanceToCampus,
    required int totalRooms,
    required String description,
    required List<int> facilityIds,
    required List<int> ruleIds,
    List<XFile> photos = const [],
  }) async {
    try {
      await ApiService.multipart(
        'POST',
        '/owner/koses',
        _kosFields(
          name: name,
          price: price,
          genderType: genderType,
          location: location,
          latitude: latitude,
          longitude: longitude,
          distanceToCampus: distanceToCampus,
          totalRooms: totalRooms,
          description: description,
          facilityIds: facilityIds,
          ruleIds: ruleIds,
        ),
        photos: photos,
      );
      await fetchKoses();
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Terjadi kesalahan tak terduga. Coba lagi.';
    }
  }

  Future<String?> updateKos({
    required int id,
    required String name,
    required int price,
    required String genderType,
    required String location,
    double? latitude,
    double? longitude,
    required double distanceToCampus,
    required int totalRooms,
    required String description,
    required List<int> facilityIds,
    required List<int> ruleIds,
    List<XFile> photos = const [],
  }) async {
    try {
      await ApiService.multipart(
        'PUT',
        '/owner/koses/$id',
        _kosFields(
          name: name,
          price: price,
          genderType: genderType,
          location: location,
          latitude: latitude,
          longitude: longitude,
          distanceToCampus: distanceToCampus,
          totalRooms: totalRooms,
          description: description,
          facilityIds: facilityIds,
          ruleIds: ruleIds,
        ),
        photos: photos,
      );
      await fetchKoses();
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Terjadi kesalahan tak terduga. Coba lagi.';
    }
  }

  Future<bool> deleteKos(int id) async {
    try {
      await ApiService.delete('/owner/koses/$id');
      await fetchKoses();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteImage(int kosId, int imageId) async {
    try {
      await ApiService.delete('/owner/koses/$kosId/images/$imageId');
      await fetchKoses();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Breakdown tipe kamar OPSIONAL (mis. "Kamar AC" vs "Kamar Standar",
  /// harga beda) -- MURNI tampilan, lihat backend KosRoomType untuk kenapa
  /// ini tidak memengaruhi price/total_rooms kos itu sendiri sama sekali.
  /// Balas objek yang baru dibuat/diubah (bukan cuma bool) supaya layar
  /// bisa update tampilannya langsung tanpa fetchKoses() bolak-balik ke
  /// server -- widget.kos di layar form tidak pernah berubah sendiri
  /// (immutable), jadi state lokal di sana yang perlu di-refresh.
  Future<KosRoomType?> addRoomType(int kosId, {required String name, required int price, required int totalRooms}) async {
    try {
      final response = await ApiService.post('/owner/koses/$kosId/room-types', {
        'name': name,
        'price': price,
        'total_rooms': totalRooms,
      });
      return KosRoomType.fromJson(jsonDecode(response.body)['room_type']);
    } catch (_) {
      return null;
    }
  }

  Future<KosRoomType?> updateRoomType(int kosId, int roomTypeId, {required String name, required int price, required int totalRooms}) async {
    try {
      final response = await ApiService.put('/owner/koses/$kosId/room-types/$roomTypeId', {
        'name': name,
        'price': price,
        'total_rooms': totalRooms,
      });
      return KosRoomType.fromJson(jsonDecode(response.body)['room_type']);
    } catch (_) {
      return null;
    }
  }

  Future<bool> deleteRoomType(int kosId, int roomTypeId) async {
    try {
      await ApiService.delete('/owner/koses/$kosId/room-types/$roomTypeId');
      return true;
    } catch (_) {
      return false;
    }
  }
}
