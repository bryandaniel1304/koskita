/// Satu pesan dalam percakapan penyewa <-> pemilik.
class Message {
  final int id;
  final int senderId;
  final int receiverId;
  final int? kosId;
  final String? kosName;
  final String body;
  final String? photoUrl;
  final DateTime? readAt;
  final DateTime createdAt;

  Message({
    required this.id,
    required this.senderId,
    required this.receiverId,
    this.kosId,
    this.kosName,
    required this.body,
    this.photoUrl,
    this.readAt,
    required this.createdAt,
  });

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      id: json['id'] ?? 0,
      senderId: json['sender_id'] ?? 0,
      receiverId: json['receiver_id'] ?? 0,
      kosId: json['kos_id'],
      kosName: json['kos']?['name'],
      body: json['body'] ?? '',
      photoUrl: json['photo_url'],
      readAt: json['read_at'] != null ? DateTime.tryParse(json['read_at']) : null,
      createdAt: DateTime.parse(json['created_at']),
    );
  }
}

/// Satu baris di daftar percakapan -- ringkasan lawan bicara + pesan
/// terakhir + jumlah belum dibaca, dipakai layar "Pesan".
class Conversation {
  final int userId;
  final String userName;
  final String userRole;
  final Message? lastMessage;
  final int unreadCount;

  Conversation({
    required this.userId,
    required this.userName,
    required this.userRole,
    this.lastMessage,
    this.unreadCount = 0,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final user = json['user'] ?? {};
    return Conversation(
      userId: user['id'] ?? 0,
      userName: user['name'] ?? 'Pengguna',
      userRole: user['role'] ?? 'user',
      lastMessage: json['last_message'] != null ? Message.fromJson(json['last_message']) : null,
      unreadCount: json['unread_count'] ?? 0,
    );
  }
}
