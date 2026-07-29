import 'facility.dart';
import 'rule.dart';
import 'kos_image.dart';

class Kos {
  final int id;
  final String name;
  final int price;
  final String genderType;
  final String location;
  final double distanceToCampus;
  final String description;
  final String imageUrl;
  final String coverImage;
  final List<Facility> facilities;
  final List<Rule> rules;
  final List<KosImage> images;

  Kos({
    required this.id,
    required this.name,
    required this.price,
    required this.genderType,
    required this.location,
    required this.distanceToCampus,
    required this.description,
    required this.imageUrl,
    required this.coverImage,
    required this.facilities,
    required this.rules,
    required this.images,
  });

  factory Kos.fromJson(Map<String, dynamic> json) {
    var facilitiesList = json['facilities'] as List? ?? [];
    List<Facility> parsedFacilities = facilitiesList.map((i) => Facility.fromJson(i)).toList();

    var rulesList = json['rules'] as List? ?? [];
    List<Rule> parsedRules = rulesList.map((i) => Rule.fromJson(i)).toList();

    var imagesList = json['images'] as List? ?? [];
    List<KosImage> parsedImages = imagesList.map((i) => KosImage.fromJson(i)).toList();

    final imageUrl = json['image_url'] ?? '';

    return Kos(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      price: json['price'] ?? 0,
      genderType: json['gender_type'] ?? '',
      location: json['location'] ?? '',
      distanceToCampus: (json['distance_to_campus'] as num?)?.toDouble() ?? 0.0,
      description: json['description'] ?? '',
      imageUrl: imageUrl,
      coverImage: json['cover_image'] ?? imageUrl,
      facilities: parsedFacilities,
      rules: parsedRules,
      images: parsedImages,
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
      'distance_to_campus': distanceToCampus,
      'description': description,
      'image_url': imageUrl,
      'facilities': facilities.map((f) => f.toJson()).toList(),
      'rules': rules.map((r) => r.toJson()).toList(),
    };
  }
}
