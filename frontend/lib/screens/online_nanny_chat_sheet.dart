import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/chatbot_provider.dart';
import '../models/chat_message.dart';
import '../models/kos.dart';

/// Lembar chat "Online Nanny" -- dibuka sebagai modal bottom sheet dari
/// bubble melayang, bisa dipanggil dari halaman manapun.
class OnlineNannyChatSheet extends StatefulWidget {
  const OnlineNannyChatSheet({super.key});

  @override
  State<OnlineNannyChatSheet> createState() => _OnlineNannyChatSheetState();
}

class _OnlineNannyChatSheetState extends State<OnlineNannyChatSheet> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  void _send([String? quick]) {
    final text = quick ?? _controller.text;
    if (text.trim().isEmpty) return;
    Provider.of<ChatbotProvider>(context, listen: false).sendMessage(text);
    _controller.clear();
    _scrollToBottom();
  }

  static const _quickReplies = [
    (icon: Icons.auto_awesome_rounded, text: 'Rekomendasiin kos buat aku'),
    (icon: Icons.savings_rounded, text: 'Kos murah di BSD'),
    (icon: Icons.event_available_rounded, text: 'Cara booking'),
  ];

  @override
  Widget build(BuildContext context) {
    final chatbot = Provider.of<ChatbotProvider>(context);
    _scrollToBottom();

    return FractionallySizedBox(
      heightFactor: 0.85,
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            _buildHeader(context),
            Expanded(
              child: ListView.builder(
                controller: _scrollController,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                itemCount: chatbot.messages.length + (chatbot.isLoading ? 1 : 0),
                itemBuilder: (context, index) {
                  if (index == chatbot.messages.length) {
                    return _buildTypingIndicator();
                  }
                  return _buildMessageBubble(context, chatbot.messages[index]);
                },
              ),
            ),
            if (chatbot.messages.length <= 1) _buildQuickReplies(),
            _buildInputBar(chatbot),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 12, 12),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: [Color(0xFF7091F9), Color(0xFF355DDB)]),
            ),
            child: const Center(child: Text('👵', style: TextStyle(fontSize: 22))),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Warna adaptif tema -- 0xFF0F172A hardcode nyaris hitam,
                // tak kebaca di sheet mode gelap (scaffoldBackgroundColor
                // gelap di sana).
                Text('Online Nanny', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Theme.of(context).textTheme.bodyLarge?.color)),
                const Row(
                  children: [
                    Icon(Icons.circle, size: 8, color: Color(0xFF10B981)),
                    SizedBox(width: 4),
                    Text('Online, siap bantu', style: TextStyle(fontSize: 12, color: Colors.grey)),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close_rounded, color: Colors.grey),
            tooltip: 'Tutup chat',
            onPressed: () => Navigator.of(context).pop(),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickReplies() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Coba tanya ini',
            style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Colors.grey.shade500, letterSpacing: 0.2),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _quickReplies.map((q) {
              return Material(
                color: const Color(0xFF355DDB).withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(20),
                child: InkWell(
                  onTap: () => _send(q.text),
                  borderRadius: BorderRadius.circular(20),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(q.icon, size: 15, color: const Color(0xFF355DDB)),
                        const SizedBox(width: 6),
                        Text(
                          q.text,
                          style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: Color(0xFF355DDB)),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildTypingIndicator() {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const SizedBox(
          width: 24,
          height: 12,
          child: Center(
            child: SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF7091F9)),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildMessageBubble(BuildContext context, ChatMessage message) {
    final isUser = message.isUser;
    return Column(
      crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
      children: [
        Align(
          alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
            decoration: BoxDecoration(
              gradient: isUser
                  ? const LinearGradient(colors: [Color(0xFF7091F9), Color(0xFF355DDB)])
                  : null,
              color: isUser ? null : Theme.of(context).cardColor,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(16),
                topRight: const Radius.circular(16),
                bottomLeft: Radius.circular(isUser ? 16 : 4),
                bottomRight: Radius.circular(isUser ? 4 : 16),
              ),
            ),
            child: Text(
              message.text,
              style: TextStyle(color: isUser ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color, fontSize: 14, height: 1.4),
            ),
          ),
        ),
        if (message.kosSuggestions.isNotEmpty)
          SizedBox(
            height: 148,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: message.kosSuggestions.length,
              itemBuilder: (context, i) => _buildKosSuggestionCard(context, message.kosSuggestions[i]),
            ),
          ),
        if (message.kosSuggestions.isNotEmpty) const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildKosSuggestionCard(BuildContext context, Kos kos) {
    return GestureDetector(
      onTap: () {
        Navigator.of(context).pop();
        context.push('/kos/${kos.id}');
      },
      child: Container(
        width: 150,
        margin: const EdgeInsets.only(right: 10, bottom: 12),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 6, offset: const Offset(0, 2))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: CachedNetworkImage(
                imageUrl: kos.coverImage,
                height: 72,
                width: double.infinity,
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(height: 72, color: Colors.grey[200]),
                errorWidget: (context, url, error) => Container(height: 72, color: Colors.grey[300], child: const Icon(Icons.image, color: Colors.grey, size: 20)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    kos.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, height: 1.2),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    kos.price >= 1000000 ? 'Rp ${(kos.price / 1000000).toStringAsFixed(1)} jt' : 'Rp ${kos.price}',
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF355DDB), height: 1.2),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInputBar(ChatbotProvider chatbot) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 8, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _controller,
              decoration: InputDecoration(
                hintText: 'Tanya Online Nanny...',
                filled: true,
                fillColor: Theme.of(context).cardColor,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
              ),
              onSubmitted: (_) => _send(),
            ),
          ),
          const SizedBox(width: 8),
          Container(
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(colors: [Color(0xFF7091F9), Color(0xFF355DDB)]),
            ),
            child: IconButton(
              icon: const Icon(Icons.send_rounded, color: Colors.white),
              tooltip: 'Kirim pesan',
              onPressed: chatbot.isLoading ? null : () => _send(),
            ),
          ),
        ],
      ),
    );
  }
}
