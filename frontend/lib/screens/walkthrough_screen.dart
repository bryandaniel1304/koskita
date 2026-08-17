import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../config/app_theme.dart';
import '../widgets/gradient_blob_background.dart';
import '../widgets/premium_button.dart';

class _WalkthroughSlide {
  final IconData icon;
  final String title;
  final String description;
  const _WalkthroughSlide({required this.icon, required this.title, required this.description});
}

/// Tur singkat 4 slide yang cuma tampil SEKALI di percobaan pertama buka
/// app (ditandai lewat SharedPreferences) -- standar aplikasi modern
/// supaya pengguna baru langsung paham fitur inti sebelum daftar/masuk.
class WalkthroughScreen extends StatefulWidget {
  const WalkthroughScreen({super.key});

  @override
  State<WalkthroughScreen> createState() => _WalkthroughScreenState();
}

class _WalkthroughScreenState extends State<WalkthroughScreen> {
  final _pageController = PageController();
  int _currentPage = 0;

  static const _slides = [
    _WalkthroughSlide(
      icon: Icons.auto_awesome_rounded,
      title: 'Rekomendasi Otomatis',
      description: 'Cukup isi preferensimu -- KosKita langsung mencarikan kos yang paling cocok, tanpa perlu scroll ratusan listing.',
    ),
    _WalkthroughSlide(
      icon: Icons.chat_bubble_rounded,
      title: 'Online Nanny Siap Bantu',
      description: 'Ada pertanyaan soal kos, harga, atau cara booking? Tanya langsung ke asisten chat Online Nanny kapan saja.',
    ),
    _WalkthroughSlide(
      icon: Icons.calendar_month_rounded,
      title: 'Booking Tanpa Ribet',
      description: 'Suka satu kos? Ajukan booking langsung dari app, pantau statusnya, dan hubungi pemiliknya dengan mudah.',
    ),
    _WalkthroughSlide(
      icon: Icons.home_work_rounded,
      title: 'Punya Kos? Kelola di Sini',
      description: 'Daftar sebagai Penyedia Kos untuk memasang listing, mengelola booking masuk, dan menemukan penyewa yang paling cocok.',
    ),
  ];

  Future<void> _finish() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('has_seen_walkthrough', true);
    if (!mounted) return;
    context.go('/login');
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: GradientBlobBackground(
        child: SafeArea(
          child: Column(
            children: [
              Align(
                alignment: Alignment.topRight,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: TextButton(
                    onPressed: _finish,
                    style: TextButton.styleFrom(foregroundColor: AppTheme.muted),
                    child: const Text('Lewati', style: TextStyle(fontWeight: FontWeight.w600)),
                  ),
                ),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  itemCount: _slides.length,
                  onPageChanged: (i) => setState(() => _currentPage = i),
                  itemBuilder: (context, index) {
                    final slide = _slides[index];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 148,
                            height: 148,
                            decoration: BoxDecoration(
                              gradient: AppTheme.primaryGradient,
                              shape: BoxShape.circle,
                              boxShadow: AppTheme.glowShadow(AppTheme.primary, opacity: 0.3),
                            ),
                            child: Icon(slide.icon, size: 66, color: Colors.white),
                          )
                              .animate(key: ValueKey('icon-$index'))
                              .fadeIn(duration: 380.ms)
                              .scale(begin: const Offset(0.82, 0.82), curve: Curves.easeOutBack, duration: 420.ms),
                          const SizedBox(height: 40),
                          Text(
                            slide.title,
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.headlineSmall,
                          ).animate(key: ValueKey('title-$index')).fadeIn(delay: 100.ms, duration: 320.ms).slideY(begin: 0.15, end: 0),
                          const SizedBox(height: 12),
                          Text(
                            slide.description,
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ).animate(key: ValueKey('desc-$index')).fadeIn(delay: 160.ms, duration: 320.ms).slideY(begin: 0.15, end: 0),
                        ],
                      ),
                    );
                  },
                ),
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(_slides.length, (i) {
                  final active = i == _currentPage;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    curve: Curves.easeOut,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    width: active ? 24 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: active ? AppTheme.primary : const Color(0xFFE2E8F0),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  );
                }),
              ),
              Padding(
                padding: const EdgeInsets.all(24),
                child: PremiumButton(
                  label: _currentPage == _slides.length - 1 ? 'Mulai' : 'Lanjut',
                  onPressed: () {
                    if (_currentPage == _slides.length - 1) {
                      _finish();
                    } else {
                      _pageController.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOut);
                    }
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
