import 'user_profile.dart';

class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final bool isEmailVerified;
  final UserProfile? profile;
  final String ownerVerificationStatus;
  final String? qrisUrl;
  final String? avatarUrl;
  final bool twoFactorEnabled;
  final bool notifyBookings;
  final bool notifyMessages;
  final bool notifyWaitlist;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    required this.isEmailVerified,
    this.profile,
    this.ownerVerificationStatus = 'none',
    this.qrisUrl,
    this.avatarUrl,
    this.twoFactorEnabled = false,
    this.notifyBookings = true,
    this.notifyMessages = true,
    this.notifyWaitlist = true,
  });

  bool get isVerifiedOwner => ownerVerificationStatus == 'approved';

  static bool _asBool(dynamic value, {bool fallback = true}) {
    if (value == null) return fallback;
    return value == true || value == 1;
  }

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      role: json['role'] ?? 'user',
      isEmailVerified: json['email_verified_at'] != null,
      profile: json['profile'] != null ? UserProfile.fromJson(json['profile']) : null,
      ownerVerificationStatus: json['owner_verification_status'] ?? 'none',
      qrisUrl: json['qris_url'],
      avatarUrl: json['avatar_url'],
      twoFactorEnabled: json['two_factor_enabled'] == true || json['two_factor_enabled'] == 1,
      notifyBookings: _asBool(json['notify_bookings']),
      notifyMessages: _asBool(json['notify_messages']),
      notifyWaitlist: _asBool(json['notify_waitlist']),
    );
  }
}
