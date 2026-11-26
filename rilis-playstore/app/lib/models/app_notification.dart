/// Notifikasi in-app -- hasil "derived" dari backend (status booking
/// berubah, ulasan baru masuk), bukan push notification sungguhan.
class AppNotification {
  final String id;
  final String type;
  final String title;
  final String message;
  final int? kosId;
  final DateTime createdAt;

  AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    this.kosId,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id']?.toString() ?? '',
      type: json['type'] ?? '',
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      kosId: json['kos_id'],
      createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
    );
  }

  bool get isPositive => type == 'booking_confirmed' || type == 'new_review' || type == 'booking_completed';
  bool get isNegative => type == 'booking_rejected' || type == 'booking_cancelled';
}
