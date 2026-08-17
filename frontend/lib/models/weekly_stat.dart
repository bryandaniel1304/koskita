/// Satu titik data mingguan untuk grafik analitik dashboard pemilik.
class WeeklyStat {
  final String label;
  final int bookings;
  final int reviews;

  WeeklyStat({required this.label, required this.bookings, required this.reviews});

  factory WeeklyStat.fromJson(Map<String, dynamic> json) {
    return WeeklyStat(
      label: json['label'] ?? '',
      bookings: json['bookings'] ?? 0,
      reviews: json['reviews'] ?? 0,
    );
  }
}
