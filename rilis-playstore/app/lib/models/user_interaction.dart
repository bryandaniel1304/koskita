class UserInteraction {
  final int id;
  final int userId;
  final int kosId;
  final int? rating;
  final bool isFavorite;
  final int clickCount;

  UserInteraction({
    required this.id,
    required this.userId,
    required this.kosId,
    this.rating,
    required this.isFavorite,
    required this.clickCount,
  });

  factory UserInteraction.fromJson(Map<String, dynamic> json) {
    return UserInteraction(
      id: json['id'] ?? 0,
      userId: json['user_id'] ?? 0,
      kosId: json['kos_id'] ?? 0,
      rating: json['rating'],
      isFavorite: (json['is_favorite'] == 1 || json['is_favorite'] == true || json['is_favorite'] == '1'),
      clickCount: json['click_count'] ?? 0,
    );
  }
}
