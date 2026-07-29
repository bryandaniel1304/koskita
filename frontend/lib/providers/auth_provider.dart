import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

class AuthProvider with ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  String? _token;
  String? _errorMessage;

  AuthProvider() {
    ApiService.onUnauthorized = _handleUnauthorized;
  }

  User? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _token != null;
  String? get token => _token;
  String? get errorMessage => _errorMessage;

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  void _handleUnauthorized() {
    if (_token == null) return;
    _token = null;
    _user = null;
    notifyListeners();
  }

  Future<void> tryAutoLogin() async {
    final token = await ApiService.getToken();
    if (token == null) return;

    try {
      final response = await ApiService.get('/profile');
      final userData = jsonDecode(response.body);
      _token = token;
      _user = User.fromJson(userData);
      notifyListeners();
    } on ApiException catch (_) {
      // Token tidak valid lagi / server tidak terjangkau saat startup.
      _token = null;
      _user = null;
    }
  }

  Future<bool> login(String email, String password) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/login', {
        'email': email,
        'password': password,
      });
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      _setLoading(false);
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.type == ApiErrorType.unknown
          ? 'Email atau password salah.'
          : e.message;
    }
    _setLoading(false);
    return false;
  }

  Future<bool> register(String name, String email, String password) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.post('/register', {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      });
      final data = jsonDecode(response.body);
      _token = data['access_token'];
      _user = User.fromJson(data['user']);
      await ApiService.saveToken(_token!);
      _setLoading(false);
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _setLoading(false);
    return false;
  }

  Future<void> logout() async {
    _setLoading(true);
    try {
      await ApiService.post('/logout', {});
    } on ApiException catch (_) {
      // Tetap lanjut hapus token lokal walau request logout gagal.
    }
    await ApiService.removeToken();
    _token = null;
    _user = null;
    _setLoading(false);
  }

  Future<bool> updateProfile(Map<String, dynamic> profileData) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      await ApiService.post('/profile', profileData);
      await tryAutoLogin();
      _setLoading(false);
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _setLoading(false);
    return false;
  }

  Future<bool> resetInteractions() async {
    _setLoading(true);
    try {
      await ApiService.post('/profile/reset-interactions', {});
      await tryAutoLogin();
      _setLoading(false);
      return true;
    } on ApiException catch (_) {
      // Abaikan; UI cukup tahu operasi gagal lewat return false.
    }
    _setLoading(false);
    return false;
  }
}
