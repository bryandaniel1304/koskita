import 'user_profile.dart';

class User {
  final int id;
  final String name;
  final String email;
  final String role;
  final UserProfile? profile;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.profile,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      role: json['role'] ?? 'user',
      profile: json['profile'] != null ? UserProfile.fromJson(json['profile']) : null,
    );
  }
}
