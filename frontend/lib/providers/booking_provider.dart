import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/booking.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';
import '../services/notification_scheduler.dart';
import '../services/review_prompt_service.dart';

class BookingProvider with ChangeNotifier {
  List<Booking> _bookings = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<Booking> get bookings => _bookings;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchBookings() async {
    _errorMessage = null;
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/bookings');
      final List<dynamic> data = jsonDecode(response.body);
      _bookings = data.map((b) => Booking.fromJson(b)).toList();
      // Sinkronkan pengingat lokal dengan status booking terbaru -- jadwal
      // otomatis mengikuti booking baru dikonfirmasi/dibatalkan.
      unawaited(NotificationScheduler.syncWithBookings(_bookings));
      // Momen positif yang terukur -- booking pertama yang confirmed,
      // bukan momen acak. Service-nya sendiri yang jaga supaya cuma
      // ditawarkan sekali seumur akun.
      unawaited(ReviewPromptService.maybePromptAfterConfirmedBooking(_bookings.any((b) => b.status == 'confirmed')));
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (_) {
      // Jaring pengaman -- tanpa ini, exception non-ApiException (mis.
      // respons sukses tapi body tak terduga) akan lolos tanpa pernah
      // mematikan status loading, bikin layar nyangkut loading selamanya.
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Mengajukan booking baru. Mengembalikan pesan error (jika gagal) atau
  /// null jika sukses, supaya form bisa menampilkan alasan gagal yang jelas
  /// (mis. error validasi tanggal dari backend).
  Future<String?> createBooking({
    required int kosId,
    required DateTime startDate,
    required int durationMonths,
    String? notes,
  }) async {
    try {
      await ApiService.post('/bookings', {
        'kos_id': kosId,
        'start_date': startDate.toIso8601String().split('T').first,
        'duration_months': durationMonths,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });
      await fetchBookings();
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Terjadi kesalahan tak terduga. Coba lagi.';
    }
  }

  Future<bool> cancelBooking(int id) async {
    try {
      await ApiService.post('/bookings/$id/cancel', {});
      await fetchBookings();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }
}
