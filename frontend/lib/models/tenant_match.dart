/// Hasil pencocokan terbalik: profil penyewa yang paling cocok dengan satu
/// kos milik pemilik, dihasilkan dari ContentBasedFilter::calculateScoresForKos
/// di backend.
class TenantMatch {
  final int userId;
  final String name;
  final String gender;
  final String occupation;
  final int budgetMin;
  final int budgetMax;
  final String preferredLocation;
  final List<String> preferredFacilities;
  final List<String> preferredRules;
  final int matchPercentage;

  TenantMatch({
    required this.userId,
    required this.name,
    required this.gender,
    required this.occupation,
    required this.budgetMin,
    required this.budgetMax,
    required this.preferredLocation,
    required this.preferredFacilities,
    required this.preferredRules,
    required this.matchPercentage,
  });

  factory TenantMatch.fromJson(Map<String, dynamic> json) {
    List<String> asStringList(dynamic value) {
      if (value is List) return value.map((e) => e.toString()).toList();
      return [];
    }

    return TenantMatch(
      userId: json['user']?['id'] ?? 0,
      name: json['user']?['name'] ?? 'Pengguna',
      gender: json['gender'] ?? '',
      occupation: json['occupation'] ?? '',
      budgetMin: json['budget_min'] ?? 0,
      budgetMax: json['budget_max'] ?? 0,
      preferredLocation: json['preferred_location'] ?? '',
      preferredFacilities: asStringList(json['preferred_facilities']),
      preferredRules: asStringList(json['preferred_rules']),
      matchPercentage: (json['match_percentage'] as num?)?.toInt() ?? 0,
    );
  }
}
