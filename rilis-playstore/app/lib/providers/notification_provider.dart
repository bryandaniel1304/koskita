import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/app_notification.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

/// State notifikasi in-app -- dipakai baik oleh akun penyewa maupun
/// pemilik lewat endpoint yang sama (`/notifications`, isinya beda
/// otomatis mengikuti role di backend). Di-polling (fetch manual/pull to
/// refresh), bukan push -- lihat catatan di NotificationController.
class NotificationProvider with ChangeNotifier {
  List<AppNotification> _notifications = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<AppNotification> get notifications => _notifications;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchNotifications() async {
    _errorMessage = null;
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/notifications');
      final data = jsonDecode(response.body);
      final List<dynamic> list = data['notifications'] ?? [];
      _notifications = list.map((n) => AppNotification.fromJson(n)).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (_) {
      _errorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
