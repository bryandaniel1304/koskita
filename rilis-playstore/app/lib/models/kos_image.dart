class KosImage {
  final int id;
  final String url;
  final bool isCover;

  KosImage({required this.id, required this.url, required this.isCover});

  factory KosImage.fromJson(Map<String, dynamic> json) {
    return KosImage(
      id: json['id'] ?? 0,
      url: json['url'] ?? '',
      isCover: (json['is_cover'] == 1 || json['is_cover'] == true),
    );
  }
}
