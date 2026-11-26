import 'package:flutter/material.dart';
import '../config/app_theme.dart';

/// Ilustrasi custom untuk layar kosong -- gambar "kartu list kosong"
/// (garis putus-putus mewakili baris data yang belum ada) + lencana ikon
/// kontekstual di sudut, menggantikan ikon Material polos supaya layar
/// kosong terasa seperti bagian dari produk yang dirancang, bukan
/// placeholder bawaan.
class EmptyStateIllustration extends StatelessWidget {
  final IconData icon;
  final double size;

  const EmptyStateIllustration({super.key, required this.icon, this.size = 128});

  @override
  Widget build(BuildContext context) {
    final lineColor = Theme.of(context).brightness == Brightness.dark
        ? AppTheme.primaryLight.withValues(alpha: 0.35)
        : AppTheme.primary.withValues(alpha: 0.22);
    final cardColor = AppTheme.primary.withValues(alpha: 0.05);

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        clipBehavior: Clip.none,
        alignment: Alignment.center,
        children: [
          CustomPaint(
            size: Size(size, size),
            painter: _EmptyCardPainter(lineColor: lineColor, cardColor: cardColor),
          ),
          Positioned(
            bottom: size * 0.06,
            right: size * 0.06,
            child: Container(
              width: size * 0.34,
              height: size * 0.34,
              decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
              alignment: Alignment.center,
              child: Icon(icon, size: size * 0.17, color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyCardPainter extends CustomPainter {
  final Color lineColor;
  final Color cardColor;

  _EmptyCardPainter({required this.lineColor, required this.cardColor});

  @override
  void paint(Canvas canvas, Size size) {
    final cardRect = Rect.fromLTWH(size.width * 0.12, size.height * 0.14, size.width * 0.68, size.height * 0.68);
    final cardRRect = RRect.fromRectAndRadius(cardRect, Radius.circular(size.width * 0.09));

    final fillPaint = Paint()..color = cardColor;
    canvas.drawRRect(cardRRect, fillPaint);

    final borderPaint = Paint()
      ..color = lineColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.018;
    canvas.drawRRect(cardRRect, borderPaint);

    // Baris-baris putus-putus di dalam kartu -- merepresentasikan data yang
    // belum ada, bukan sekadar dekorasi.
    final dashPaint = Paint()
      ..color = lineColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.024
      ..strokeCap = StrokeCap.round;

    final rowYs = [0.34, 0.48, 0.62].map((f) => size.height * f).toList();
    final rowWidths = [0.44, 0.36, 0.4];
    for (var i = 0; i < rowYs.length; i++) {
      _drawDashedLine(
        canvas,
        Offset(size.width * 0.24, rowYs[i]),
        Offset(size.width * (0.24 + rowWidths[i]), rowYs[i]),
        dashPaint,
      );
    }
  }

  void _drawDashedLine(Canvas canvas, Offset start, Offset end, Paint paint) {
    const dashWidth = 6.0;
    const dashSpace = 5.0;
    final totalLength = (end - start).distance;
    final direction = (end - start) / totalLength;
    double covered = 0;
    while (covered < totalLength) {
      final segmentEnd = (covered + dashWidth) < totalLength ? covered + dashWidth : totalLength;
      canvas.drawLine(start + direction * covered, start + direction * segmentEnd, paint);
      covered += dashWidth + dashSpace;
    }
  }

  @override
  bool shouldRepaint(covariant _EmptyCardPainter oldDelegate) => oldDelegate.lineColor != lineColor || oldDelegate.cardColor != cardColor;
}
