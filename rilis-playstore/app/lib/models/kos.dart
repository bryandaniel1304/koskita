import 'facility.dart';
import 'rule.dart';
import 'kos_image.dart';
import 'review.dart';
import 'kos_room_type.dart';

class Kos {
  final int id;
  final String name;
  final int price;
  final String genderType;
  final String location;
  final double? latitude;
  final double? longitude;
  final double distanceToCampus;
  final String description;
  final String imageUrl;
  final String coverImage;
  final String? ownerName;
  final int? ownerId;
  final bool ownerVerified;
  final DateTime? verifiedAt;
  final String? ownerResponseBadge;
  final double? averageReviewRating;
  final int reviewsCount;
  final List<Review> reviews;
  final int totalRooms;
  final int occupiedRooms;
  final int availableRooms;
  final List<Facility> facilities;
  final List<Rule> rules;
  final List<KosImage> images;
  final List<KosRoomType> roomTypes;
  /// Jumlah ulasan per bintang, kunci 1-5 -- dipakai grafik batang
  /// "rating breakdown" di halaman detail.
  final Map<int, int> ratingBreakdown;
  /// true kalau pengguna login boleh menulis ulasan BARU untuk kos ini --
  /// wajib masa sewanya sudah selesai (bukan cuma "confirmed"), ATAU
  /// sudah pernah menulis ulasan sebelumnya (boleh diedit tanpa syarat
  /// itu). Lihat backend Api\KosController::show().
  final bool canReview;

  Kos({
    required this.id,
    required this.name,
    required this.price,
    required this.genderType,
    required this.location,
    this.latitude,
    this.longitude,
    required this.distanceToCampus,
    required this.description,
    required this.imageUrl,
    required this.coverImage,
    this.ownerName,
    this.ownerId,
    this.ownerVerified = false,
    this.verifiedAt,
    this.ownerResponseBadge,
    this.averageReviewRating,
    this.reviewsCount = 0,
    this.reviews = const [],
    this.totalRooms = 1,
    this.occupiedRooms = 0,
    this.availableRooms = 1,
    required this.facilities,
    required this.rules,
    required this.images,
    this.roomTypes = const [],
    this.canReview = false,
    Map<int, int>? ratingBreakdown,
  }) : ratingBreakdown = ratingBreakdown ?? const {5: 0, 4: 0, 3: 0, 2: 0, 1: 0};

  /// Parse angka yang bisa datang sebagai num ATAU string dari backend --
  /// kolom DECIMAL di Laravel (mis. latitude/longitude) di-serialize
  /// sebagai string JSON kalau modelnya tidak diberi cast eksplisit. Dulu
  /// field ini dipaksa `as num?` langsung dan meledak (TypeError) begitu
  /// backend kirim string, bikin seluruh daftar kos gagal ke-parse. Helper
  /// ini jadi lapisan jaga-jaga tambahan di sisi app, di luar perbaikan di
  /// backend-nya sendiri.
  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }

  factory Kos.fromJson(Map<String, dynamic> json) {
    var facilitiesList = json['facilities'] as List? ?? [];
    List<Facility> parsedFacilities = facilitiesList.map((i) => Facility.fromJson(i)).toList();

    var rulesList = json['rules'] as List? ?? [];
    List<Rule> parsedRules = rulesList.map((i) => Rule.fromJson(i)).toList();

    var imagesList = json['images'] as List? ?? [];
    List<KosImage> parsedImages = imagesList.map((i) => KosImage.fromJson(i)).toList();

    var reviewsList = json['reviews'] as List? ?? [];
    List<Review> parsedReviews = reviewsList.map((i) => Review.fromJson(i)).toList();

    var roomTypesList = json['room_types'] as List? ?? [];
    List<KosRoomType> parsedRoomTypes = roomTypesList.map((i) => KosRoomType.fromJson(i)).toList();

    final rawBreakdown = json['rating_breakdown'] as Map<String, dynamic>?;
    final parsedBreakdown = <int, int>{
      for (final star in [5, 4, 3, 2, 1]) star: (rawBreakdown?[star.toString()] as num?)?.toInt() ?? 0,
    };

    final imageUrl = json['image_url'] ?? '';

    return Kos(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      price: json['price'] ?? 0,
      genderType: json['gender_type'] ?? '',
      location: json['location'] ?? '',
      latitude: _parseDouble(json['latitude']),
      longitude: _parseDouble(json['longitude']),
      distanceToCampus: _parseDouble(json['distance_to_campus']) ?? 0.0,
      description: json['description'] ?? '',
      imageUrl: imageUrl,
      coverImage: json['cover_image'] ?? imageUrl,
      ownerName: json['owner']?['name'],
      // Fallback ke kolom mentah 'owner_id' -- beberapa endpoint (mis.
      // /api/bookings) tidak selalu eager-load relasi 'owner' lengkap,
      // tapi kolomnya sendiri selalu ada di response.
      ownerId: json['owner']?['id'] ?? json['owner_id'],
      ownerVerified: json['owner']?['owner_verification_status'] == 'approved',
      verifiedAt: json['verified_at'] != null ? DateTime.tryParse(json['verified_at']) : null,
      ownerResponseBadge: json['owner_response_badge'],
      averageReviewRating: _parseDouble(json['average_review_rating']),
      reviewsCount: json['reviews_count'] ?? 0,
      reviews: parsedReviews,
      totalRooms: json['total_rooms'] ?? 1,
      occupiedRooms: json['occupied_rooms'] ?? 0,
      availableRooms: json['available_rooms'] ?? 1,
      facilities: parsedFacilities,
      rules: parsedRules,
      images: parsedImages,
      roomTypes: parsedRoomTypes,
      canReview: json['can_review'] == true,
      ratingBreakdown: parsedBreakdown,
    );
  }

  /// Semua foto untuk galeri: kalau backend belum punya foto upload sama
  /// sekali, fallback ke cover/image_url tunggal supaya UI tetap ada gambar.
  List<String> get galleryUrls {
    if (images.isNotEmpty) {
      return images.map((img) => img.url).toList();
    }
    return coverImage.isNotEmpty ? [coverImage] : [];
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'price': price,
      'gender_type': genderType,
      'location': location,
      'latitude': latitude,
      'longitude': longitude,
      'distance_to_campus': distanceToCampus,
      'description': description,
      'image_url': imageUrl,
      'cover_image': coverImage,
      'average_review_rating': averageReviewRating,
      'reviews_count': reviewsCount,
      'total_rooms': totalRooms,
      'occupied_rooms': occupiedRooms,
      'available_rooms': availableRooms,
      'facilities': facilities.map((f) => f.toJson()).toList(),
      'rules': rules.map((r) => r.toJson()).toList(),
    };
  }
}
