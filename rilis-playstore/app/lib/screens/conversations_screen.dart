import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/message_provider.dart';
import '../models/message.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../config/app_theme.dart';

/// Daftar percakapan (satu baris per lawan bicara) -- dipakai penyewa
/// maupun pemilik, isinya otomatis beda tergantung siapa yang sudah pernah
/// saling kirim pesan (lihat MessageController::conversations).
class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<MessageProvider>(context, listen: false).fetchConversations();
    });
  }

  String _timeLabel(DateTime dt) {
    final now = DateTime.now();
    if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
      return DateFormat('HH:mm').format(dt);
    }
    return DateFormat('d MMM', 'id_ID').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<MessageProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Pesan')),
      body: RefreshIndicator(
        onRefresh: () => provider.fetchConversations(),
        child: Builder(builder: (context) {
          if (provider.isLoadingConversations && provider.conversations.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [
                SkeletonBox(height: 72, borderRadius: BorderRadius.all(Radius.circular(16))),
                SizedBox(height: 12),
                SkeletonBox(height: 72, borderRadius: BorderRadius.all(Radius.circular(16))),
              ],
            );
          }
          if (provider.errorMessage != null && provider.conversations.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: provider.errorMessage!, onRetry: () => provider.fetchConversations()),
              ],
            );
          }
          if (provider.conversations.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada percakapan.\nMulai chat dari halaman detail kos atau booking.',
                  icon: Icons.chat_bubble_outline_rounded,
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.all(20),
            itemCount: provider.conversations.length,
            itemBuilder: (context, index) {
              final Conversation c = provider.conversations[index];
              final hasUnread = c.unreadCount > 0;
              return GestureDetector(
                onTap: () async {
                  // Buka thread bisa menandai pesan sudah dibaca di server --
                  // refresh daftar percakapan begitu balik supaya badge unread
                  // di sini ikut update, bukan nyangkut dari sebelum dibuka.
                  await context.push('/messages/${c.userId}', extra: c.userName);
                  if (context.mounted) {
                    Provider.of<MessageProvider>(context, listen: false).fetchConversations();
                  }
                },
                child: Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: AppTheme.softShadow(opacity: 0.05),
                    border: hasUnread ? Border.all(color: AppTheme.primary.withValues(alpha: 0.3)) : null,
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
                        alignment: Alignment.center,
                        child: Text(
                          c.userName.isNotEmpty ? c.userName[0].toUpperCase() : '?',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(c.userName, style: TextStyle(fontWeight: hasUnread ? FontWeight.w800 : FontWeight.w600, fontSize: 13.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                                ),
                                if (c.lastMessage != null)
                                  Text(_timeLabel(c.lastMessage!.createdAt), style: TextStyle(fontSize: 10.5, color: Theme.of(context).textTheme.bodySmall?.color?.withValues(alpha: 0.7))),
                              ],
                            ),
                            const SizedBox(height: 3),
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    c.lastMessage?.body ?? '',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      color: hasUnread ? Theme.of(context).textTheme.bodyMedium?.color : AppTheme.muted,
                                      fontWeight: hasUnread ? FontWeight.w600 : FontWeight.w400,
                                    ),
                                  ),
                                ),
                                if (hasUnread) ...[
                                  const SizedBox(width: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                    decoration: BoxDecoration(color: AppTheme.primary, borderRadius: BorderRadius.circular(20)),
                                    child: Text('${c.unreadCount}', style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w800)),
                                  ),
                                ],
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ).animate(delay: (index.clamp(0, 8) * 40).ms).fadeIn(duration: 240.ms);
            },
          );
        }),
      ),
    );
  }
}
