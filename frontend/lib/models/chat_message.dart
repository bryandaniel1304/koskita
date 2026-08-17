import 'kos.dart';

class ChatMessage {
  final String text;
  final bool isUser;
  final List<Kos> kosSuggestions;
  final DateTime timestamp;

  ChatMessage({
    required this.text,
    required this.isUser,
    this.kosSuggestions = const [],
    DateTime? timestamp,
  }) : timestamp = timestamp ?? DateTime.now();
}
