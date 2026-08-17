import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import '../models/weekly_stat.dart';
import '../config/app_theme.dart';

/// Grafik batang aktivitas mingguan (booking masuk & ulasan baru) untuk
/// dashboard pemilik -- lihat catatan di OwnerKosController::analytics()
/// soal kenapa cuma dua metrik ini (bukan "jumlah dilihat per minggu",
/// yang datanya tidak tersedia per-kejadian di backend).
class WeeklyActivityChart extends StatelessWidget {
  final List<WeeklyStat> weeks;

  const WeeklyActivityChart({super.key, required this.weeks});

  @override
  Widget build(BuildContext context) {
    if (weeks.isEmpty) {
      return const SizedBox.shrink();
    }
    final maxValue = weeks.fold<int>(1, (max, w) => [max, w.bookings, w.reviews].reduce((a, b) => a > b ? a : b));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            _legendDot(AppTheme.primary, 'Booking Masuk'),
            const SizedBox(width: 16),
            _legendDot(AppTheme.warning, 'Ulasan Baru'),
          ],
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 160,
          child: BarChart(
            BarChartData(
              maxY: (maxValue + 1).toDouble(),
              gridData: const FlGridData(show: false),
              borderData: FlBorderData(show: false),
              titlesData: FlTitlesData(
                leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                bottomTitles: AxisTitles(
                  sideTitles: SideTitles(
                    showTitles: true,
                    reservedSize: 26,
                    getTitlesWidget: (value, meta) {
                      final index = value.toInt();
                      if (index < 0 || index >= weeks.length) return const SizedBox.shrink();
                      return Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(weeks[index].label, style: const TextStyle(fontSize: 9.5, color: AppTheme.muted)),
                      );
                    },
                  ),
                ),
              ),
              barGroups: List.generate(weeks.length, (index) {
                final week = weeks[index];
                return BarChartGroupData(
                  x: index,
                  barsSpace: 4,
                  barRods: [
                    BarChartRodData(toY: week.bookings.toDouble(), color: AppTheme.primary, width: 8, borderRadius: BorderRadius.circular(3)),
                    BarChartRodData(toY: week.reviews.toDouble(), color: AppTheme.warning, width: 8, borderRadius: BorderRadius.circular(3)),
                  ],
                );
              }),
            ),
          ),
        ),
      ],
    );
  }

  Widget _legendDot(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 8, height: 8, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.muted)),
      ],
    );
  }
}
