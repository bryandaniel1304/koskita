class UserProfile {
  final int id;
  final int userId;
  final String gender;
  final String occupation;
  final int budgetMin;
  final int budgetMax;
  final List<String> preferredFacilities;
  final List<String> preferredRules;
  final String preferredLocation;

  UserProfile({
    required this.id,
    required this.userId,
    required this.gender,
    required this.occupation,
    required this.budgetMin,
    required this.budgetMax,
    required this.preferredFacilities,
    required this.preferredRules,
    required this.preferredLocation,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    var pFacilities = json['preferred_facilities'];
    List<String> parsedFacilities = [];
    if (pFacilities is List) {
      parsedFacilities = pFacilities.map((i) => i.toString()).toList();
    }

    var pRules = json['preferred_rules'];
    List<String> parsedRules = [];
    if (pRules is List) {
      parsedRules = pRules.map((i) => i.toString()).toList();
    }

    return UserProfile(
      id: json['id'] ?? 0,
      userId: json['user_id'] ?? 0,
      gender: json['gender'] ?? 'pria',
      occupation: json['occupation'] ?? 'mahasiswa',
      budgetMin: json['budget_min'] ?? 1000000,
      budgetMax: json['budget_max'] ?? 3000000,
      preferredFacilities: parsedFacilities,
      preferredRules: parsedRules,
      preferredLocation: json['preferred_location'] ?? 'Karawaci',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'gender': gender,
      'occupation': occupation,
      'budget_min': budgetMin,
      'budget_max': budgetMax,
      'preferred_facilities': preferredFacilities,
      'preferred_rules': preferredRules,
      'preferred_location': preferredLocation,
    };
  }
}
