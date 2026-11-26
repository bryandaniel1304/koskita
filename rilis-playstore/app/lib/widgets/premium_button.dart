import 'package:flutter/material.dart';
import '../config/app_theme.dart';

/// Tombol CTA gradient dengan animasi "tekan" (scale mengecil sedikit saat
/// ditahan) -- ElevatedButton bawaan tidak bisa gradient langsung, jadi
/// dibungkus manual di sini supaya semua tombol utama di app konsisten
/// terasa "hidup" saat disentuh, bukan cuma warna flat statis.
class PremiumButton extends StatefulWidget {
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool expand;
  final Gradient? gradient;

  const PremiumButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = true,
    this.gradient,
  });

  @override
  State<PremiumButton> createState() => _PremiumButtonState();
}

class _PremiumButtonState extends State<PremiumButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    // "Tidak bisa ditekan" (loading ATAU memang tidak ada aksi) beda dari
    // "terlihat nonaktif/abu-abu" (cuma kalau memang tidak ada aksi sama
    // sekali) -- saat loading, tombol harus TETAP kelihatan warna gradasi
    // brand-nya, bukan berubah pudar abu-abu seolah rusak/tidak aktif.
    final notTappable = widget.onPressed == null || widget.loading;
    final greyedOut = widget.onPressed == null && !widget.loading;

    return GestureDetector(
      onTapDown: notTappable ? null : (_) => setState(() => _pressed = true),
      onTapCancel: notTappable ? null : () => setState(() => _pressed = false),
      onTapUp: notTappable ? null : (_) => setState(() => _pressed = false),
      onTap: notTappable ? null : widget.onPressed,
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: Container(
          width: widget.expand ? double.infinity : null,
          padding: const EdgeInsets.symmetric(vertical: 17, horizontal: 24),
          decoration: BoxDecoration(
            gradient: greyedOut ? null : (widget.gradient ?? AppTheme.primaryGradient),
            color: greyedOut ? const Color(0xFFE2E8F0) : null,
            borderRadius: BorderRadius.circular(16),
            boxShadow: greyedOut ? null : AppTheme.glowShadow(AppTheme.primary, opacity: 0.32),
          ),
          child: Row(
            mainAxisSize: widget.expand ? MainAxisSize.max : MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (widget.loading) ...[
                const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2.4, valueColor: AlwaysStoppedAnimation(Colors.white)),
                ),
                const SizedBox(width: 12),
              ] else if (widget.icon != null) ...[
                Icon(widget.icon, color: greyedOut ? const Color(0xFF94A3B8) : Colors.white, size: 19),
                const SizedBox(width: 10),
              ],
              Flexible(
                child: Text(
                  widget.label,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: greyedOut ? const Color(0xFF64748B) : Colors.white, fontWeight: FontWeight.w700, fontSize: 15),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
