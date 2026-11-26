/// Statistik privat satu kos yang cuma relevan buat pemiliknya sendiri --
/// dikembalikan bareng detail kos dari GET /owner/koses/{id}.
class OwnerKosStats {
  final int totalViews;
  final int totalFavorites;
  final int totalRatings;
  final double? avgRating;

  OwnerKosStats({
    required this.totalViews,
    required this.totalFavorites,
    required this.totalRatings,
    this.avgRating,
  });

  factory OwnerKosStats.fromJson(Map<String, dynamic> json) {
    return OwnerKosStats(
      totalViews: json['total_views'] ?? 0,
      totalFavorites: json['total_favorites'] ?? 0,
      totalRatings: json['total_ratings'] ?? 0,
      avgRating: (json['avg_rating'] as num?)?.toDouble(),
    );
  }
}
