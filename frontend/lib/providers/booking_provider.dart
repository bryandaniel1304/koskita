import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/booking.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

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
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _isLoading = false;
    notifyListeners();
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
    }
  }

  Future<bool> cancelBooking(int id) async {
    try {
      await ApiService.post('/bookings/$id/cancel', {});
      await fetchBookings();
      return true;
    } on ApiException catch (_) {
      return false;
    }
  }
}
