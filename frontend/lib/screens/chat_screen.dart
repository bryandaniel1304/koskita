import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/message_provider.dart';
import '../providers/auth_provider.dart';
import '../models/message.dart';
import '../config/app_theme.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../utils/haptics.dart';
import '../utils/image_source_picker.dart';
import 'photo_gallery_screen.dart';

/// Satu thread percakapan dengan satu lawan bicara (identitas lawan bicara
/// cuma nama, dikirim lewat `extra` -- sengaja bukan objek User utuh,
/// supaya tetap ringan & tahan kalau `extra` hilang setelah proses
/// Android dipulihkan, lihat catatan _extraAsKos di app_router.dart).
class ChatScreen extends StatefulWidget {
  final int partnerId;
  final String? partnerName;
  final int? kosId;

  const ChatScreen({super.key, required this.partnerId, this.partnerName, this.kosId});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  bool _isSending = false;
  XFile? _pendingPhoto;

  // Template balasan cepat khusus pemilik -- mempercepat waktu respons
  // pertama, yang langsung menaikkan badge "Respons Cepat" di kos mereka.
  // Set statis (bukan dikelola per-pemilik dari server) supaya langsung
  // bisa dipakai tanpa perlu layar pengaturan tambahan.
  static const _ownerQuickReplies = [
    'Masih kosong, silakan ajukan booking ya.',
    'Sudah penuh untuk saat ini, coba cek lagi minggu depan.',
    'Boleh datang langsung untuk lihat kondisi kamar.',
    'Baik, nanti saya kabari lebih lanjut.',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final provider = Provider.of<MessageProvider>(context, listen: false);
      await provider.fetchThread(widget.partnerId);
      _scrollToBottom();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    if (!_scrollController.hasClients) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
      }
    });
  }

  Future<void> _pickPhoto() async {
    final photo = await pickImageWithSourceChoice(context, title: 'Foto untuk Dikirim');
    if (photo == null || !mounted) return;
    setState(() => _pendingPhoto = photo);
  }

  void _removePendingPhoto() {
    setState(() => _pendingPhoto = null);
  }

  void _openPhoto(BuildContext context, String url) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PhotoGalleryScreen(images: [url])),
    );
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    final photo = _pendingPhoto;
    // Boleh kirim kalau ADA salah satu (teks atau foto) -- tidak wajib
    // dua-duanya, sama seperti pesan cuma-foto ala WhatsApp.
    if ((text.isEmpty && photo == null) || _isSending) return;
    setState(() => _isSending = true);
    final provider = Provider.of<MessageProvider>(context, listen: false);
    final result = await provider.sendMessage(receiverId: widget.partnerId, body: text, kosId: widget.kosId, photo: photo);
    if (!mounted) return;
    setState(() => _isSending = false);
    switch (result) {
      case MessageSendResult.sent:
        Haptics.light();
        _controller.clear();
        setState(() => _pendingPhoto = null);
        _scrollToBottom();
        break;
      case MessageSendResult.queued:
        // Bukan gagal permanen -- teks tetap dikosongkan karena pesannya
        // sudah "aman" tersimpan di antrian lokal, akan otomatis terkirim
        // begitu koneksi pulih (lihat MessageProvider._flushOfflineQueue).
        _controller.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tidak ada koneksi -- pesan akan terkirim otomatis saat online kembali.')),
        );
        break;
      case MessageSendResult.failed:
        Haptics.error();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal mengirim pesan. Coba lagi.')),
        );
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = Provider.of<MessageProvider>(context);
    final authUser = Provider.of<AuthProvider>(context, listen: false).user;
    final myId = authUser?.id;
    final isOwner = authUser?.role == 'owner';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text(widget.partnerName ?? 'Pesan')),
      body: Column(
        children: [
          if (provider.pendingQueueCount > 0)
            Container(
              width: double.infinity,
              color: AppTheme.warning.withValues(alpha: 0.12),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  const Icon(Icons.schedule_send_rounded, size: 15, color: Color(0xFFB45309)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      provider.pendingQueueCount == 1
                          ? '1 pesan menunggu koneksi untuk terkirim.'
                          : '${provider.pendingQueueCount} pesan menunggu koneksi untuk terkirim.',
                      style: const TextStyle(fontSize: 11.5, color: Color(0xFF92400E), fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: provider.isLoadingThread && provider.thread.isEmpty
                ? const _ThreadSkeleton()
                : provider.errorMessage != null && provider.thread.isEmpty
                    ? ErrorStateView(
                        message: provider.errorMessage!,
                        onRetry: () => provider.fetchThread(widget.partnerId),
                      )
                    : provider.thread.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            'Belum ada pesan. Mulai percakapan di bawah ini.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.all(16),
                        itemCount: provider.thread.length,
                        itemBuilder: (context, index) {
                          final Message m = provider.thread[index];
                          final isMe = m.senderId == myId;
                          return Align(
                            alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 10),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
                              decoration: BoxDecoration(
                                gradient: isMe ? AppTheme.primaryGradient : null,
                                color: isMe ? null : Theme.of(context).cardColor,
                                borderRadius: BorderRadius.only(
                                  topLeft: const Radius.circular(16),
                                  topRight: const Radius.circular(16),
                                  bottomLeft: Radius.circular(isMe ? 16 : 4),
                                  bottomRight: Radius.circular(isMe ? 4 : 16),
                                ),
                                boxShadow: AppTheme.softShadow(opacity: 0.04),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (m.kosName != null) ...[
                                    Text(
                                      'Tentang: ${m.kosName}',
                                      style: TextStyle(fontSize: 10.5, fontStyle: FontStyle.italic, color: isMe ? Colors.white.withValues(alpha: 0.85) : AppTheme.muted),
                                    ),
                                    const SizedBox(height: 3),
                                  ],
                                  if (m.photoUrl != null) ...[
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(12),
                                      child: GestureDetector(
                                        onTap: () => _openPhoto(context, m.photoUrl!),
                                        child: CachedNetworkImage(
                                          imageUrl: m.photoUrl!,
                                          width: 180,
                                          height: 180,
                                          fit: BoxFit.cover,
                                          placeholder: (context, url) => const SkeletonBox(width: 180, height: 180),
                                          errorWidget: (context, url, error) => Container(
                                            width: 180,
                                            height: 180,
                                            color: Colors.grey[300],
                                            child: const Icon(Icons.broken_image_rounded, color: Colors.grey),
                                          ),
                                        ),
                                      ),
                                    ),
                                    if (m.body.isNotEmpty) const SizedBox(height: 6),
                                  ],
                                  if (m.body.isNotEmpty)
                                    Text(m.body, style: TextStyle(color: isMe ? Colors.white : Theme.of(context).textTheme.bodyLarge?.color, fontSize: 14)),
                                  const SizedBox(height: 4),
                                  Text(
                                    DateFormat('HH:mm').format(m.createdAt),
                                    style: TextStyle(fontSize: 10, color: isMe ? Colors.white.withValues(alpha: 0.75) : AppTheme.muted),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8, offset: const Offset(0, -2))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (isOwner) ...[
                    SizedBox(
                      height: 34,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _ownerQuickReplies.length,
                        separatorBuilder: (context, index) => const SizedBox(width: 8),
                        itemBuilder: (context, index) {
                          final reply = _ownerQuickReplies[index];
                          return ActionChip(
                            label: Text(reply, style: const TextStyle(fontSize: 11.5)),
                            onPressed: () {
                              _controller.text = reply;
                              _controller.selection = TextSelection.fromPosition(TextPosition(offset: _controller.text.length));
                            },
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  if (_pendingPhoto != null) ...[
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.file(File(_pendingPhoto!.path), width: 56, height: 56, fit: BoxFit.cover),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text('Foto siap dikirim', style: Theme.of(context).textTheme.bodySmall),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close_rounded, size: 18),
                            tooltip: 'Batalkan foto',
                            onPressed: _removePendingPhoto,
                          ),
                        ],
                      ),
                    ),
                  ],
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.camera_alt_rounded, color: AppTheme.muted),
                        tooltip: 'Lampirkan foto',
                        onPressed: _isSending ? null : _pickPhoto,
                      ),
                      Expanded(
                        child: TextField(
                          controller: _controller,
                          minLines: 1,
                          maxLines: 4,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: const InputDecoration(hintText: 'Tulis pesan...'),
                          onSubmitted: (_) => _send(),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
                        child: IconButton(
                          icon: _isSending
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
                          tooltip: 'Kirim pesan',
                          onPressed: _isSending ? null : _send,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Skeleton loading buat thread chat -- meniru bubble pesan berselang-seling
/// kiri/kanan selagi fetch pertama, gantikan spinner polos di tengah layar.
class _ThreadSkeleton extends StatelessWidget {
  const _ThreadSkeleton();

  @override
  Widget build(BuildContext context) {
    final widths = [180.0, 130.0, 210.0, 150.0, 170.0];
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: widths.length,
      itemBuilder: (context, index) {
        final isMe = index.isOdd;
        return Align(
          alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: SkeletonBox(
              width: widths[index],
              height: 40,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(16),
                topRight: const Radius.circular(16),
                bottomLeft: Radius.circular(isMe ? 16 : 4),
                bottomRight: Radius.circular(isMe ? 4 : 16),
              ),
            ),
          ),
        );
      },
    );
  }
}
