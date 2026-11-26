/// Breakdown tipe kamar OPSIONAL per kos (mis. "Kamar AC" Rp1.8jt vs
/// "Kamar Standar" Rp1.2jt) -- MURNI tampilan, tidak dipakai di alur
/// booking (penyewa tetap mengajukan booking ke kos secara umum, bukan
/// ke tipe kamar spesifik). Lihat backend KosRoomType untuk kenapa.
class KosRoomType {
  final int id;
  final String name;
  final int price;
  final int totalRooms;

  KosRoomType({
    required this.id,
    required this.name,
    required this.price,
    required this.totalRooms,
  });

  factory KosRoomType.fromJson(Map<String, dynamic> json) {
    return KosRoomType(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      price: json['price'] ?? 0,
      totalRooms: json['total_rooms'] ?? 0,
    );
  }
}
