import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/kos.dart';
import '../models/user_interaction.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

class KosProvider with ChangeNotifier {
  List<Kos> _koses = [];
  List<Map<String, dynamic>> _recommendations = [];
  List<Kos> _favorites = [];
  bool _isColdStart = true;
  int _ratingCount = 0;
  bool _isLoading = false;
  bool _isFavoritesLoading = false;
  String? _errorMessage;
  String? _favoritesErrorMessage;

  Kos? _currentKos;
  UserInteraction? _currentInteraction;

  List<Kos> get koses => _koses;
  List<Map<String, dynamic>> get recommendations => _recommendations;
  List<Kos> get favorites => _favorites;
  bool get isColdStart => _isColdStart;
  int get ratingCount => _ratingCount;
  bool get isLoading => _isLoading;
  bool get isFavoritesLoading => _isFavoritesLoading;
  String? get errorMessage => _errorMessage;
  String? get favoritesErrorMessage => _favoritesErrorMessage;

  Kos? get currentKos => _currentKos;
  UserInteraction? get currentInteraction => _currentInteraction;

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  Future<void> fetchKoses({String? search, String? genderType, String? location}) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      List<String> params = [];
      if (search != null && search.isNotEmpty) params.add('search=$search');
      if (genderType != null && genderType.isNotEmpty) params.add('gender_type=$genderType');
      if (location != null && location.isNotEmpty) params.add('location=$location');
      final query = params.isNotEmpty ? '?${params.join('&')}' : '';

      final response = await ApiService.get('/kos$query');
      final List<dynamic> data = jsonDecode(response.body);
      _koses = data.map((i) => Kos.fromJson(i)).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _setLoading(false);
  }

  Future<void> fetchKosDetail(int id) async {
    _currentKos = null;
    _currentInteraction = null;
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.get('/kos/$id');
      final data = jsonDecode(response.body);
      _currentKos = Kos.fromJson(data['kos']);
      if (data['user_interaction'] != null) {
        _currentInteraction = UserInteraction.fromJson(data['user_interaction']);
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _setLoading(false);
  }

  Future<void> fetchRecommendations() async {
    _errorMessage = null;
    _setLoading(true);
    try {
      final response = await ApiService.get('/recommendations');
      final data = jsonDecode(response.body);
      _isColdStart = data['is_cold_start'] ?? true;
      _ratingCount = data['rating_count'] ?? 0;

      final List<dynamic> recs = data['recommendations'] ?? [];
      _recommendations = recs.map((r) {
        return {
          'kos': Kos.fromJson(r['kos']),
          'score_cb': (r['score_cb'] as num?)?.toDouble() ?? 0.0,
          'score_cf': (r['score_cf'] as num?)?.toDouble() ?? 0.0,
          'score_hybrid': (r['score_hybrid'] as num?)?.toDouble() ?? 0.0,
          'match_percentage': r['match_percentage'] ?? 0,
        };
      }).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    }
    _setLoading(false);
  }

  Future<void> fetchFavorites() async {
    _favoritesErrorMessage = null;
    _isFavoritesLoading = true;
    notifyListeners();
    try {
      final response = await ApiService.get('/favorites');
      final List<dynamic> data = jsonDecode(response.body);
      _favorites = data.map((i) => Kos.fromJson(i)).toList();
    } on ApiException catch (e) {
      _favoritesErrorMessage = e.message;
    }
    _isFavoritesLoading = false;
    notifyListeners();
  }

  Future<bool> rateKos(int id, {int? rating, bool? isFavorite}) async {
    try {
      final Map<String, dynamic> body = {};
      if (rating != null) body['rating'] = rating;
      if (isFavorite != null) body['is_favorite'] = isFavorite;

      final response = await ApiService.post('/kos/$id/rate', body);
      final data = jsonDecode(response.body);
      _currentInteraction = UserInteraction.fromJson(data['interaction']);

      // Refresh rekomendasi setelah rating/favorit berubah supaya hasil
      // yang tampil ke user selalu mengikuti interaksi terbaru.
      await fetchRecommendations();
      notifyListeners();
      return true;
    } on ApiException catch (_) {
      return false;
    }
  }
}
