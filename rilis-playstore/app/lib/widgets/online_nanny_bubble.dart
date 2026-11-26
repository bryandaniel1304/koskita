import 'package:flutter/material.dart';
import '../screens/online_nanny_chat_sheet.dart';

/// Tombol bubble melayang untuk membuka chat "Online Nanny" -- dibuat
/// terasa "hidup" lewat animasi pulse pelan supaya menarik perhatian
/// tanpa mengganggu.
class OnlineNannyBubble extends StatefulWidget {
  const OnlineNannyBubble({super.key});

  @override
  State<OnlineNannyBubble> createState() => _OnlineNannyBubbleState();
}

class _OnlineNannyBubbleState extends State<OnlineNannyBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulseController;
  late final Animation<double> _pulse;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1600),
    )..repeat(reverse: true);
    _pulse = Tween<double>(begin: 1.0, end: 1.08).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _pulseController.dispose();
    super.dispose();
  }

  void _openChat(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const OnlineNannyChatSheet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Buka chat Online Nanny',
      child: GestureDetector(
        onTap: () => _openChat(context),
        child: ScaleTransition(
          scale: _pulse,
          child: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: const LinearGradient(
                colors: [Color(0xFF7091F9), Color(0xFF355DDB)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF355DDB).withValues(alpha: 0.4),
                  blurRadius: 16,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Stack(
              children: [
                const Center(child: Text('👵', style: TextStyle(fontSize: 22))),
                Positioned(
                  right: 4,
                  top: 4,
                  child: Container(
                    width: 12,
                    height: 12,
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
