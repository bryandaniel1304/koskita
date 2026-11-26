import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_theme.dart';

class OnboardingTip {
  final IconData icon;
  final String title;
  final String description;

  const OnboardingTip({required this.icon, required this.title, required this.description});
}

/// Tur pengenalan fitur ringan -- BUKAN coachmark presisi yang menunjuk ke
/// widget tertentu di layar (butuh package tambahan untuk itu), melainkan
/// carousel tips singkat yang tampil SEKALI lewat bottom sheet begitu
/// pengguna pertama kali buka layar utama perannya. Status "sudah dilihat"
/// disimpan per kunci di SharedPreferences supaya tidak muncul berulang.
class OnboardingTipsSheet {
  static Future<void> showOnceIfNeeded({
    required BuildContext context,
    required String storageKey,
    required String headline,
    required List<OnboardingTip> tips,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getBool(storageKey) ?? false) return;
    if (!context.mounted) return;

    await prefs.setBool(storageKey, true);

    final pageController = PageController();
    int currentPage = 0;

    if (!context.mounted) return;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            return Container(
              padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
              decoration: BoxDecoration(
                color: Theme.of(sheetContext).scaffoldBackgroundColor,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(headline, style: Theme.of(sheetContext).textTheme.titleLarge, textAlign: TextAlign.center),
                  const SizedBox(height: 20),
                  SizedBox(
                    height: 190,
                    child: PageView.builder(
                      controller: pageController,
                      itemCount: tips.length,
                      onPageChanged: (i) => setSheetState(() => currentPage = i),
                      itemBuilder: (context, i) {
                        final tip = tips[i];
                        return Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              width: 64,
                              height: 64,
                              decoration: const BoxDecoration(gradient: AppTheme.primaryGradient, shape: BoxShape.circle),
                              alignment: Alignment.center,
                              child: Icon(tip.icon, color: Colors.white, size: 28),
                            ),
                            const SizedBox(height: 14),
                            Text(tip.title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16), textAlign: TextAlign.center),
                            const SizedBox(height: 6),
                            Text(tip.description, style: Theme.of(context).textTheme.bodySmall, textAlign: TextAlign.center),
                          ],
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(
                      tips.length,
                      (i) => Container(
                        width: 7,
                        height: 7,
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: i == currentPage ? AppTheme.primary : AppTheme.primary.withValues(alpha: 0.2),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {
                        if (currentPage < tips.length - 1) {
                          pageController.nextPage(duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
                        } else {
                          Navigator.pop(sheetContext);
                        }
                      },
                      child: Text(currentPage < tips.length - 1 ? 'Lanjut' : 'Mulai'),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
