/// Review publik untuk satu kos -- tampil ke pengguna lain, beda dari
/// rating privat yang cuma dipakai mesin rekomendasi.
class Review {
  final int id;
  final String userName;
  final int rating;
  final String? comment;
  final String? photoUrl;
  final DateTime createdAt;
  final String? ownerReply;
  final DateTime? ownerRepliedAt;

  Review({
    required this.id,
    required this.userName,
    required this.rating,
    this.comment,
    this.photoUrl,
    required this.createdAt,
    this.ownerReply,
    this.ownerRepliedAt,
  });

  factory Review.fromJson(Map<String, dynamic> json) {
    return Review(
      id: json['id'] ?? 0,
      userName: json['user']?['name'] ?? 'Pengguna',
      rating: json['rating'] ?? 0,
      comment: json['comment'],
      photoUrl: json['photo_url'],
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
      ownerReply: json['owner_reply'],
      ownerRepliedAt: json['owner_replied_at'] != null ? DateTime.tryParse(json['owner_replied_at']) : null,
    );
  }
}
