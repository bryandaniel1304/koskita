/// Kombinasi filter pencarian yang disimpan pengguna secara lokal (mis.
/// "Deket kampus, budget 1-2jt") supaya bisa dipakai ulang tanpa mengatur
/// ulang tiap field satu-satu. Murni penyimpanan di perangkat (SharedPreferences)
/// -- tidak ada endpoint API, tidak disinkronkan lintas perangkat.
class SavedFilter {
  final String id;
  final String name;
  final String? search;
  final String? genderType;
  final String? location;
  final int? budgetMin;
  final int? budgetMax;
  final List<int> facilityIds;

  SavedFilter({
    required this.id,
    required this.name,
    this.search,
    this.genderType,
    this.location,
    this.budgetMin,
    this.budgetMax,
    this.facilityIds = const [],
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'search': search,
        'gender_type': genderType,
        'location': location,
        'budget_min': budgetMin,
        'budget_max': budgetMax,
        'facility_ids': facilityIds,
      };

  factory SavedFilter.fromJson(Map<String, dynamic> json) {
    return SavedFilter(
      id: json['id'] ?? DateTime.now().millisecondsSinceEpoch.toString(),
      name: json['name'] ?? 'Filter',
      search: json['search'],
      genderType: json['gender_type'],
      location: json['location'],
      budgetMin: json['budget_min'],
      budgetMax: json['budget_max'],
      facilityIds: (json['facility_ids'] as List?)?.map((e) => e as int).toList() ?? [],
    );
  }
}
