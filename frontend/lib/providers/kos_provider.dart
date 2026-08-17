import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/kos.dart';
import '../models/facility.dart';
import '../models/user_interaction.dart';
import '../models/saved_filter.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';
import '../services/app_widget_sync_service.dart';

class KosProvider with ChangeNotifier {
  List<Kos> _koses = [];
  List<Map<String, dynamic>> _recommendations = [];
  List<Kos> _favorites = [];
  List<Facility> _facilities = [];
  bool _isColdStart = true;
  int _ratingCount = 0;
  bool _isLoading = false;
  bool _isRecommendationsLoading = false;
  bool _isFavoritesLoading = false;
  String? _errorMessage;
  String? _favoritesErrorMessage;
  /// true kalau daftar kos yang tampil sekarang berasal dari cache lokal
  /// (fetch terakhir gagal, mis. lagi offline) -- bukan data terbaru dari
  /// server. Dipakai tampilkan banner kecil "menampilkan data tersimpan".
  bool _isShowingCachedKoses = false;

  /// Sama seperti [_isShowingCachedKoses] tapi untuk layar detail satu kos
  /// -- true kalau detail yang tampil sekarang diambil dari riwayat
  /// "Terakhir Dilihat" lokal (fetch ke server gagal), bukan data terbaru.
  bool _isShowingCachedKosDetail = false;

  Kos? _currentKos;
  UserInteraction? _currentInteraction;
  Map<String, dynamic>? _currentRecommendation;

  List<Kos> get koses => _koses;
  List<Map<String, dynamic>> get recommendations => _recommendations;
  List<Kos> get favorites => _favorites;
  List<Facility> get facilities => _facilities;
  bool get isColdStart => _isColdStart;
  int get ratingCount => _ratingCount;
  bool get isLoading => _isLoading;
  bool get isRecommendationsLoading => _isRecommendationsLoading;
  bool get isFavoritesLoading => _isFavoritesLoading;
  String? get errorMessage => _errorMessage;
  String? get favoritesErrorMessage => _favoritesErrorMessage;
  bool get isShowingCachedKoses => _isShowingCachedKoses;
  bool get isShowingCachedKosDetail => _isShowingCachedKosDetail;

  Kos? get currentKos => _currentKos;
  UserInteraction? get currentInteraction => _currentInteraction;
  Map<String, dynamic>? get currentRecommendation => _currentRecommendation;

  static const _cacheKey = 'cached_koses_v1';
  static const _recentlyViewedKey = 'recently_viewed_koses_v1';
  static const _recentlyViewedMax = 15;

  List<Kos> _recentlyViewed = [];
  List<Kos> get recentlyViewed => _recentlyViewed;

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  /// Pesan generik dipakai kalau kegagalannya BUKAN [ApiException] (mis.
  /// respons sukses tapi bodinya tidak sesuai dugaan parser) -- supaya
  /// pengguna tetap dikasih tahu & bisa coba lagi, bukan layar diam
  /// nyangkut loading selamanya.
  static const _unexpectedErrorMessage = 'Terjadi kesalahan tak terduga. Coba lagi.';

  Future<void> _cacheKoses(List<Kos> koses) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = jsonEncode(koses.map((k) => k.toJson()).toList());
      await prefs.setString(_cacheKey, raw);
    } catch (_) {
      // Cache cuma "nice to have" -- gagal simpan tidak boleh ganggu alur utama.
    }
  }

  /// Dipanggil kalau fetch dari server gagal total (mis. tidak ada
  /// koneksi) -- tampilkan daftar kos terakhir yang berhasil disimpan
  /// (kalau ada) supaya layar tidak kosong melompong pas offline.
  Future<bool> _loadCachedKosesIfAny() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_cacheKey);
      if (raw == null) return false;
      final List<dynamic> data = jsonDecode(raw);
      _koses = data.map((i) => Kos.fromJson(i)).toList();
      return _koses.isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  // "/kos" sudah dipaginasi di backend (LengthAwarePaginator, 20/halaman) --
  // dua field ini nyimpen state buat infinite-scroll: query yang sedang
  // aktif (dipakai ulang persis sama oleh fetchMoreKoses(), cuma beda
  // parameter `page`) dan apakah masih ada halaman berikutnya.
  String _activeQuery = '';
  bool _hasMorePages = false;
  int _currentPage = 1;
  bool _isLoadingMore = false;
  bool get isLoadingMore => _isLoadingMore;
  bool get hasMorePages => _hasMorePages;

  Future<void> fetchKoses({
    String? search,
    String? genderType,
    String? location,
    int? budgetMin,
    int? budgetMax,
    List<int>? facilityIds,
    String? sort,
  }) async {
    _errorMessage = null;
    _setLoading(true);
    try {
      List<String> params = [];
      if (search != null && search.isNotEmpty) params.add('search=$search');
      if (genderType != null && genderType.isNotEmpty) params.add('gender_type=$genderType');
      if (location != null && location.isNotEmpty) params.add('location=$location');
      if (budgetMin != null) params.add('budget_min=$budgetMin');
      if (budgetMax != null) params.add('budget_max=$budgetMax');
      if (sort != null && sort.isNotEmpty) params.add('sort=$sort');
      if (facilityIds != null) {
        for (final id in facilityIds) {
          params.add('facilities[]=$id');
        }
      }
      _activeQuery = params.join('&');
      _currentPage = 1;
      final query = _activeQuery.isNotEmpty ? '?$_activeQuery' : '';

      final response = await ApiService.get('/kos$query');
      final decoded = jsonDecode(response.body);
      final List<dynamic> data = decoded['data'];
      _koses = data.map((i) => Kos.fromJson(i)).toList();
      _hasMorePages = (decoded['current_page'] ?? 1) < (decoded['last_page'] ?? 1);
      _isShowingCachedKoses = false;

      // Cache cuma untuk hasil TANPA filter (halaman pertama daftar utuh)
      // -- hasil yang sudah difilter tidak representatif dipakai sebagai
      // fallback umum.
      final isUnfiltered = search == null && genderType == null && location == null && budgetMin == null && budgetMax == null && (facilityIds == null || facilityIds.isEmpty);
      if (isUnfiltered) {
        unawaited(_cacheKoses(_koses));
      }
    } on ApiException catch (e) {
      final hadCache = await _loadCachedKosesIfAny();
      if (hadCache) {
        _isShowingCachedKoses = true;
      } else {
        _errorMessage = e.message;
      }
    } catch (_) {
      // Jaring pengaman -- mis. respons sukses tapi body-nya bukan format
      // yang diharapkan. Tanpa ini, exception non-ApiException akan lolos
      // tanpa pernah mematikan status loading (layar nyangkut loading
      // selamanya, tanpa pesan error apapun).
      _errorMessage = _unexpectedErrorMessage;
    } finally {
      _setLoading(false);
    }
  }

  /// Dipanggil saat pengguna scroll mendekati akhir daftar (lihat
  /// home_screen.dart) -- menambah halaman berikutnya ke _koses yang
  /// sudah ada, BUKAN menggantinya seperti fetchKoses(). Query yang
  /// dipakai persis sama dengan pencarian/filter yang sedang aktif.
  Future<void> fetchMoreKoses() async {
    if (_isLoadingMore || !_hasMorePages) return;

    _isLoadingMore = true;
    notifyListeners();
    try {
      final nextPage = _currentPage + 1;
      final query = [if (_activeQuery.isNotEmpty) _activeQuery, 'page=$nextPage'].join('&');
      final response = await ApiService.get('/kos?$query');
      final decoded = jsonDecode(response.body);
      final List<dynamic> data = decoded['data'];
      _koses = [..._koses, ...data.map((i) => Kos.fromJson(i))];
      _currentPage = (decoded['current_page'] ?? nextPage) as int;
      _hasMorePages = _currentPage < (decoded['last_page'] ?? _currentPage);
    } on ApiException catch (_) {
      // Gagal muat halaman berikutnya -- diam saja, daftar yang sudah ada
      // tetap tampil apa adanya, pengguna bisa coba scroll lagi nanti.
    } catch (_) {
      // Idem.
    } finally {
      _isLoadingMore = false;
      notifyListeners();
    }
  }

  Future<void> fetchFacilitiesMeta() async {
    if (_facilities.isNotEmpty) return;
    try {
      final response = await ApiService.get('/meta');
      final data = jsonDecode(response.body);
      _facilities = (data['facilities'] as List).map((f) => Facility.fromJson(f)).toList();
      notifyListeners();
    } on ApiException catch (_) {
      // Panel filter tetap bisa dipakai tanpa checklist fasilitas kalau gagal.
    } catch (_) {
      // Idem.
    }
  }

  /// Kos lain di lokasi yang sama (maks 5, kos yang sedang dibuka tidak
  /// diikutkan) -- dipakai bagian "Kos Serupa" di halaman detail.
  Future<List<Kos>> fetchSimilarKoses(Kos current) async {
    try {
      final response = await ApiService.get('/kos?location=${current.location}');
      final List<dynamic> data = jsonDecode(response.body);
      return data
          .map((i) => Kos.fromJson(i))
          .where((k) => k.id != current.id)
          .take(5)
          .toList();
    } catch (_) {
      return [];
    }
  }

  bool _onWaitlist = false;
  bool get onWaitlist => _onWaitlist;
  String? _qrisUrl;
  String? get qrisUrl => _qrisUrl;

  Future<void> fetchKosDetail(int id) async {
    _currentKos = null;
    _currentInteraction = null;
    _currentRecommendation = null;
    _onWaitlist = false;
    _qrisUrl = null;
    _errorMessage = null;
    _isShowingCachedKosDetail = false;
    _setLoading(true);
    try {
      final response = await ApiService.get('/kos/$id');
      final data = jsonDecode(response.body);
      _currentKos = Kos.fromJson(data['kos']);
      if (data['user_interaction'] != null) {
        _currentInteraction = UserInteraction.fromJson(data['user_interaction']);
      }
      _currentRecommendation = data['recommendation'];
      // Field 'similar' dari API sengaja tidak dipakai di sini -- mobile
      // sudah punya mekanisme "Kos Serupa" sendiri (lihat fetchSimilarKoses),
      // dipertahankan supaya endpoint tetap kompatibel dengan web yang
      // memakai field ini langsung.
      _onWaitlist = data['on_waitlist'] ?? false;
      _qrisUrl = data['qris_url'];
      unawaited(_addToRecentlyViewed(_currentKos!));
    } on ApiException catch (e) {
      // Kalau kegagalannya karena koneksi/timeout (bukan mis. 404/unauthorized),
      // coba tolong dengan versi kos ini dari riwayat "Terakhir Dilihat" lokal
      // -- supaya kos yang sudah pernah dibuka tetap bisa dilihat pas offline,
      // bukan cuma layar error kosong.
      final cached = (e.type == ApiErrorType.network || e.type == ApiErrorType.timeout) ? await _findCachedKos(id) : null;
      if (cached != null) {
        _currentKos = cached;
        _isShowingCachedKosDetail = true;
      } else {
        _errorMessage = e.message;
      }
    } catch (_) {
      final cached = await _findCachedKos(id);
      if (cached != null) {
        _currentKos = cached;
        _isShowingCachedKosDetail = true;
      } else {
        _errorMessage = _unexpectedErrorMessage;
      }
    } finally {
      _setLoading(false);
    }
  }

  /// Cari kos tertentu di riwayat "Terakhir Dilihat" lokal -- dipakai
  /// sebagai fallback saat fetch detail dari server gagal (offline).
  /// Cek dulu daftar in-memory (kalau Beranda sudah pernah memuatnya),
  /// baru muat dari SharedPreferences kalau belum ada.
  Future<Kos?> _findCachedKos(int id) async {
    try {
      for (final kos in _recentlyViewed) {
        if (kos.id == id) return kos;
      }
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_recentlyViewedKey);
      if (raw == null) return null;
      final List<dynamic> data = jsonDecode(raw);
      for (final item in data) {
        if (item['id'] == id) return Kos.fromJson(item);
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  /// Laporkan kos yang mencurigakan (foto tidak sesuai, kos tidak ada, dst.)
  /// -- masuk antrian moderasi admin.
  Future<bool> reportKos(int kosId, String reason, {String? details}) async {
    try {
      await ApiService.post('/reports', {
        'type': 'kos',
        'id': kosId,
        'reason': reason,
        if (details != null && details.isNotEmpty) 'details': details,
      });
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Toggle daftar tunggu "Beri Tahu Saya" untuk kos yang sedang penuh.
  Future<bool> toggleWaitlist(int kosId) async {
    try {
      if (_onWaitlist) {
        await ApiService.delete('/kos/$kosId/waitlist');
      } else {
        await ApiService.post('/kos/$kosId/waitlist', {});
      }
      _onWaitlist = !_onWaitlist;
      notifyListeners();
      return true;
    } on ApiException catch (_) {
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Simpan kos yang baru dibuka ke daftar "Terakhir Dilihat" -- disimpan
  /// LOKAL di HP (SharedPreferences), bukan di server, jadi tidak butuh
  /// endpoint/migration baru. Kos terbaru selalu di depan, duplikat
  /// dihapus (bukan digandakan), dan dibatasi 15 entri terakhir saja.
  Future<void> _addToRecentlyViewed(Kos kos) async {
    try {
      _recentlyViewed.removeWhere((k) => k.id == kos.id);
      _recentlyViewed.insert(0, kos);
      if (_recentlyViewed.length > _recentlyViewedMax) {
        _recentlyViewed = _recentlyViewed.sublist(0, _recentlyViewedMax);
      }
      final prefs = await SharedPreferences.getInstance();
      final raw = jsonEncode(_recentlyViewed.map((k) => k.toJson()).toList());
      await prefs.setString(_recentlyViewedKey, raw);
      notifyListeners();
      // Widget layar utama Android "Kos Terakhir Dilihat" -- sengaja
      // dipanggil terpisah (tidak di dalam try di atas) supaya gagal
      // update widget tidak menandai penyimpanan riwayat itu sendiri gagal.
      unawaited(AppWidgetSyncService.updateLastViewed(kos));
    } catch (_) {
      // "Nice to have" -- gagal simpan riwayat tidak boleh ganggu alur utama.
    }
  }

  /// Muat riwayat "Terakhir Dilihat" dari penyimpanan lokal -- dipanggil
  /// saat Beranda dibuka. Tidak butuh network sama sekali.
  Future<void> loadRecentlyViewed() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_recentlyViewedKey);
      if (raw == null) return;
      final List<dynamic> data = jsonDecode(raw);
      _recentlyViewed = data.map((i) => Kos.fromJson(i)).toList();
      notifyListeners();
    } catch (_) {
      // Kalau data cache korup/format lama, cukup tampil kosong -- bukan crash.
    }
  }

  static const _savedFiltersKey = 'saved_search_filters_v1';
  static const _savedFiltersMax = 8;

  List<SavedFilter> _savedFilters = [];
  List<SavedFilter> get savedFilters => _savedFilters;

  /// Muat daftar filter tersimpan dari penyimpanan lokal -- dipanggil saat
  /// Beranda dibuka, sama seperti [loadRecentlyViewed].
  Future<void> loadSavedFilters() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_savedFiltersKey);
      if (raw == null) return;
      final List<dynamic> data = jsonDecode(raw);
      _savedFilters = data.map((i) => SavedFilter.fromJson(i)).toList();
      notifyListeners();
    } catch (_) {
      // Data korup/format lama -- cukup tampil kosong, jangan crash.
    }
  }

  /// Simpan kombinasi filter aktif dengan nama pilihan pengguna. Dibatasi
  /// [_savedFiltersMax] entri -- yang tertua dibuang kalau sudah penuh,
  /// supaya daftar filter tersimpan tidak menumpuk tak terbatas.
  Future<bool> saveFilter(SavedFilter filter) async {
    try {
      _savedFilters.add(filter);
      if (_savedFilters.length > _savedFiltersMax) {
        _savedFilters = _savedFilters.sublist(_savedFilters.length - _savedFiltersMax);
      }
      final prefs = await SharedPreferences.getInstance();
      final raw = jsonEncode(_savedFilters.map((f) => f.toJson()).toList());
      await prefs.setString(_savedFiltersKey, raw);
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<void> deleteSavedFilter(String id) async {
    try {
      _savedFilters.removeWhere((f) => f.id == id);
      final prefs = await SharedPreferences.getInstance();
      final raw = jsonEncode(_savedFilters.map((f) => f.toJson()).toList());
      await prefs.setString(_savedFiltersKey, raw);
      notifyListeners();
    } catch (_) {
      // "Nice to have" -- gagal hapus tidak boleh ganggu alur utama.
    }
  }

  /// Loading rekomendasi punya flag TERPISAH dari [isLoading] (yang dipakai
  /// [fetchKoses]) -- Home memanggil keduanya sekaligus tanpa saling
  /// menunggu (`fetchKoses()` + `fetchRecommendations()`), jadi kalau
  /// keduanya berbagi satu flag yang sama, siapa pun yang selesai duluan
  /// akan mematikan status loading punya yang satunya juga (skeleton
  /// hilang sebelum datanya benar-benar siap).
  Future<void> fetchRecommendations() async {
    _errorMessage = null;
    _isRecommendationsLoading = true;
    notifyListeners();
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
          'explanation': List<String>.from(r['explanation'] ?? []),
        };
      }).toList();
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (_) {
      _errorMessage = _unexpectedErrorMessage;
    } finally {
      _isRecommendationsLoading = false;
      notifyListeners();
    }
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
    } catch (_) {
      _favoritesErrorMessage = _unexpectedErrorMessage;
    } finally {
      _isFavoritesLoading = false;
      notifyListeners();
    }
  }

  /// Kirim/perbarui review PUBLIK (beda dari rateKos di atas yang privat,
  /// dipakai mesin rekomendasi). Submit ulang otomatis update review lama,
  /// bukan bikin duplikat (di-handle backend via updateOrCreate). [photo]
  /// opsional -- kalau diisi, dikirim sebagai multipart.
  Future<String?> submitReview(int kosId, {required int rating, String? comment, XFile? photo}) async {
    try {
      await ApiService.multipart(
        'POST',
        '/kos/$kosId/reviews',
        {
          'rating': rating.toString(),
          if (comment != null && comment.isNotEmpty) 'comment': comment,
        },
        photos: photo != null ? [photo] : const [],
        fileFieldName: 'photo',
      );
      await fetchKosDetail(kosId);
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return _unexpectedErrorMessage;
    }
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
    } catch (_) {
      return false;
    }
  }
}
