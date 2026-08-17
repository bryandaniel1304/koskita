import 'dart:convert';
import 'package:flutter/material.dart';
import '../models/chat_message.dart';
import '../models/kos.dart';
import '../services/api_service.dart';
import '../services/api_exception.dart';

/// Provider untuk "Online Nanny" -- asisten chat bawaan KOSKITA yang
/// menjawab pertanyaan seputar kos berdasarkan data sungguhan di backend
/// (rule-based, bukan LLM berbayar).
class ChatbotProvider with ChangeNotifier {
  final List<ChatMessage> _messages = [];
  bool _isLoading = false;

  ChatbotProvider() {
    _messages.add(ChatMessage(
      text: 'Hai! Aku Online Nanny 👵💙 Aku bisa bantuin kamu cari kos, kasih rekomendasi, atau jawab pertanyaan soal booking & favorit. Coba tanya sesuatu, misalnya "kos murah di BSD" atau "rekomendasiin kos buat aku" ya!',
      isUser: false,
    ));
  }

  List<ChatMessage> get messages => List.unmodifiable(_messages);
  bool get isLoading => _isLoading;

  Future<void> sendMessage(String text) async {
    final trimmed = text.trim();
    if (trimmed.isEmpty || _isLoading) return;

    _messages.add(ChatMessage(text: trimmed, isUser: true));
    _isLoading = true;
    notifyListeners();

    try {
      final response = await ApiService.post('/chatbot', {'message': trimmed});
      final data = jsonDecode(response.body);
      final List<dynamic> kosJson = data['kos'] ?? [];
      final kosList = kosJson.map((k) => Kos.fromJson(k)).toList();

      _messages.add(ChatMessage(
        text: data['reply'] ?? 'Hmm, aku belum ngerti maksudnya 🤔',
        isUser: false,
        kosSuggestions: kosList,
      ));
    } on ApiException catch (e) {
      _messages.add(ChatMessage(
        text: 'Waduh, Online Nanny lagi susah dihubungi nih (${e.message}). Coba lagi sebentar ya 😅',
        isUser: false,
      ));
    } catch (_) {
      // Jaring pengaman -- tanpa ini, exception non-ApiException (mis.
      // respons sukses tapi body tak terduga) bikin indikator "mengetik"
      // nyangkut selamanya, seolah Online Nanny tidak pernah membalas.
      _messages.add(ChatMessage(
        text: 'Waduh, ada yang tidak beres di sisi Online Nanny. Coba lagi sebentar ya 😅',
        isUser: false,
      ));
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
