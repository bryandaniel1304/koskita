import 'kos.dart';

class Booking {
  final int id;
  final int kosId;
  final Kos? kos;
  final DateTime startDate;
  final int durationMonths;
  final String? notes;
  final String status;
  final String? adminNote;
  final DateTime createdAt;

  Booking({
    required this.id,
    required this.kosId,
    this.kos,
    required this.startDate,
    required this.durationMonths,
    this.notes,
    required this.status,
    this.adminNote,
    required this.createdAt,
  });

  factory Booking.fromJson(Map<String, dynamic> json) {
    return Booking(
      id: json['id'] ?? 0,
      kosId: json['kos_id'] ?? 0,
      kos: json['kos'] != null ? Kos.fromJson(json['kos']) : null,
      startDate: DateTime.parse(json['start_date']),
      durationMonths: json['duration_months'] ?? 1,
      notes: json['notes'],
      status: json['status'] ?? 'pending',
      adminNote: json['admin_note'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}
