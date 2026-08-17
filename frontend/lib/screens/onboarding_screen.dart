import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';
import '../config/app_theme.dart';

class OnboardingScreen extends StatefulWidget {
  /// true kalau layar ini dibuka langsung setelah registrasi (alur cold-start
  /// pertama kali) — menentukan apakah tombol simpan mengarah ke Beranda
  /// atau cukup kembali ke layar sebelumnya (mis. saat edit dari Profil).
  final bool fromRegistration;

  const OnboardingScreen({super.key, this.fromRegistration = false});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  // Sengaja null di awal -- tidak ada pilihan yang dianggap "sudah dipilih"
  // sebelum pengguna sendiri menyentuh chip/dropdown-nya.
  String? _gender;
  String? _occupation;
  String? _preferredLocation;

  RangeValues _budgetRange = const RangeValues(1000000, 3000000);

  final List<String> _allFacilities = ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'];
  final List<String> _preferredFacilities = [];

  final List<String> _allRules = ['Jam Malam', 'Tamu Boleh Menginap', 'Bawa Hewan', 'Merokok'];
  final List<String> _preferredRules = [];

  final PageController _pageController = PageController();
  int _currentPageIndex = 0;

  @override
  void initState() {
    super.initState();
    // Kalau dibuka dari "Edit Profil Preferensi" di layar Profil (bukan
    // registrasi baru), isi ulang semua pilihan dari profil yang sudah
    // ada -- sebelumnya form ini SELALU kosong meski buka untuk edit,
    // jadi user terpaksa isi ulang semuanya dari nol tiap kali mau ubah
    // satu preferensi saja.
    if (!widget.fromRegistration) {
      final profile = Provider.of<AuthProvider>(context, listen: false).user?.profile;
      if (profile != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          setState(() {
            _gender = profile.gender;
            _occupation = profile.occupation;
            _preferredLocation = profile.preferredLocation;
            // Clamp ke batas RangeSlider (500rb-6jt) -- jaga-jaga kalau
            // data lama pernah tersimpan di luar rentang itu, supaya
            // tidak memicu assertion error dari RangeSlider.
            _budgetRange = RangeValues(
              profile.budgetMin.toDouble().clamp(500000, 6000000),
              profile.budgetMax.toDouble().clamp(500000, 6000000),
            );
            _preferredFacilities
              ..clear()
              ..addAll(profile.preferredFacilities.where(_allFacilities.contains));
            _preferredRules
              ..clear()
              ..addAll(profile.preferredRules.where(_allRules.contains));
          });
        });
      }
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _saveProfile() async {
    if (_gender == null || _occupation == null || _preferredLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lengkapi dulu jenis kelamin, pekerjaan, dan area kampus.'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final kosProvider = Provider.of<KosProvider>(context, listen: false);

    final success = await authProvider.updateProfile({
      'gender': _gender,
      'occupation': _occupation,
      'budget_min': _budgetRange.start.round(),
      'budget_max': _budgetRange.end.round(),
      'preferred_facilities': _preferredFacilities,
      'preferred_rules': _preferredRules,
      'preferred_location': _preferredLocation,
    });

    if (!mounted) return;

    if (success) {
      // Muat ulang rekomendasi berdasarkan profil baru
      await kosProvider.fetchRecommendations();
      if (!mounted) return;
      if (widget.fromRegistration) {
        context.go('/home');
      } else {
        context.pop();
      }
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal menyimpan preferensi. Coba lagi.'),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  String _formatRupiah(double value) {
    return 'Rp ${(value / 1000000).toStringAsFixed(1)} jt';
  }

  Widget _sectionCard({required IconData icon, required String title, required Widget child, int index = 0}) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, size: 18, color: AppTheme.primary),
              ),
              const SizedBox(width: 10),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
            ],
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    ).animate(delay: (index * 70).ms).fadeIn(duration: 320.ms).slideY(begin: 0.08, end: 0);
  }

  Widget _choicePill({required String label, required bool selected, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
        decoration: BoxDecoration(
          gradient: selected ? AppTheme.primaryGradient : null,
          color: selected ? null : Theme.of(context).inputDecorationTheme.fillColor,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Text(
          label,
          style: TextStyle(color: selected ? Colors.white : AppTheme.muted, fontWeight: FontWeight.w700, fontSize: 13),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(widget.fromRegistration ? 'Preferensi Pencarian Kos' : 'Edit Preferensi'),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Welcome / Header banner
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.auto_awesome_rounded, color: AppTheme.primary, size: 18),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.fromRegistration ? 'Kenalan Dulu, Yuk!' : 'Ubah Preferensimu',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Langkah ${_currentPageIndex + 1} dari 4: ${_stepTitle(_currentPageIndex)}',
                            style: const TextStyle(fontSize: 12, color: AppTheme.muted),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Progress indicator
              Row(
                children: List.generate(4, (index) {
                  final active = index <= _currentPageIndex;
                  return Expanded(
                    child: Container(
                      height: 4,
                      margin: EdgeInsets.only(right: index == 3 ? 0 : 6),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        gradient: active ? AppTheme.primaryGradient : null,
                        color: active ? null : const Color(0xFFE2E8F0),
                      ),
                    ),
                  );
                }),
              ),
              const SizedBox(height: 20),

              // PageView Content
              Expanded(
                child: PageView(
                  controller: _pageController,
                  physics: const NeverScrollableScrollPhysics(),
                  onPageChanged: (index) {
                    setState(() {
                      _currentPageIndex = index;
                    });
                  },
                  children: [
                    // Step 1: Demographics
                    SingleChildScrollView(
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _sectionCard(
                          icon: Icons.person_outline_rounded,
                          title: 'Profil Demografis',
                          index: 1,
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Jenis Kelamin', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                                  Row(
                                    children: [
                                      _choicePill(label: 'Pria', selected: _gender == 'pria', onTap: () => setState(() => _gender = 'pria')),
                                      const SizedBox(width: 8),
                                      _choicePill(label: 'Wanita', selected: _gender == 'wanita', onTap: () => setState(() => _gender = 'wanita')),
                                    ],
                                  ),
                                ],
                              ),
                              const Divider(height: 28),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Pekerjaan', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                                  Row(
                                    children: [
                                      _choicePill(label: 'Mahasiswa', selected: _occupation == 'mahasiswa', onTap: () => setState(() => _occupation = 'mahasiswa')),
                                      const SizedBox(width: 8),
                                      _choicePill(label: 'Pekerja', selected: _occupation == 'pekerja', onTap: () => setState(() => _occupation = 'pekerja')),
                                    ],
                                  ),
                                ],
                              ),
                              const Divider(height: 28),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Area Kampus', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                                  DropdownButton<String>(
                                    value: _preferredLocation,
                                    hint: const Text('Pilih area', style: TextStyle(color: AppTheme.muted)),
                                    underline: Container(height: 2, decoration: BoxDecoration(gradient: AppTheme.primaryGradient, borderRadius: BorderRadius.circular(2))),
                                    borderRadius: BorderRadius.circular(14),
                                    onChanged: (String? newValue) {
                                      if (newValue != null) setState(() => _preferredLocation = newValue);
                                    },
                                    items: <String>['Karawaci', 'BSD', 'Serpong'].map<DropdownMenuItem<String>>((String value) {
                                      return DropdownMenuItem<String>(value: value, child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600)));
                                    }).toList(),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    // Step 2: Budget
                    SingleChildScrollView(
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _sectionCard(
                          icon: Icons.payments_outlined,
                          title: 'Anggaran Bulanan',
                          index: 2,
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Rentang Budget', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5)),
                                  Text(
                                    '${_formatRupiah(_budgetRange.start)} - ${_formatRupiah(_budgetRange.end)}',
                                    style: const TextStyle(fontWeight: FontWeight.w800, color: AppTheme.primary),
                                  ),
                                ],
                              ),
                              SliderTheme(
                                data: SliderTheme.of(context).copyWith(
                                  activeTrackColor: AppTheme.primary,
                                  inactiveTrackColor: const Color(0xFFE2E8F0),
                                  thumbColor: AppTheme.primary,
                                  overlayColor: AppTheme.primary.withValues(alpha: 0.12),
                                ),
                                child: RangeSlider(
                                  values: _budgetRange,
                                  min: 500000,
                                  max: 6000000,
                                  divisions: 11,
                                  onChanged: (values) => setState(() => _budgetRange = values),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    // Step 3: Facilities
                    SingleChildScrollView(
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _sectionCard(
                          icon: Icons.checklist_rounded,
                          title: 'Fasilitas Utama',
                          index: 3,
                          child: Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: _allFacilities.map((facility) {
                              final isSelected = _preferredFacilities.contains(facility);
                              return _choicePill(
                                label: facility,
                                selected: isSelected,
                                onTap: () => setState(() {
                                  if (isSelected) {
                                    _preferredFacilities.remove(facility);
                                  } else {
                                    _preferredFacilities.add(facility);
                                  }
                                }),
                              );
                            }).toList(),
                          ),
                        ),
                      ),
                    ),

                    // Step 4: Rules
                    SingleChildScrollView(
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _sectionCard(
                          icon: Icons.rule_rounded,
                          title: 'Aturan Kos Toleransi',
                          index: 4,
                          child: Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: _allRules.map((rule) {
                              final isSelected = _preferredRules.contains(rule);
                              return _choicePill(
                                label: rule,
                                selected: isSelected,
                                onTap: () => setState(() {
                                  if (isSelected) {
                                    _preferredRules.remove(rule);
                                  } else {
                                    _preferredRules.add(rule);
                                  }
                                }),
                              );
                            }).toList(),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Navigation Buttons
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Row(
                  children: [
                    if (_currentPageIndex > 0)
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            _pageController.previousPage(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
                            );
                          },
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            side: const BorderSide(color: AppTheme.primary, width: 1.5),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                          ),
                          child: const Text('Kembali', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary)),
                        ),
                      ),
                    if (_currentPageIndex > 0) const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: authProvider.isLoading ? null : () {
                          if (_currentPageIndex < 3) {
                            if (_currentPageIndex == 0 && (_gender == null || _occupation == null || _preferredLocation == null)) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Lengkapi dulu jenis kelamin, pekerjaan, dan area kampus.'),
                                  backgroundColor: AppTheme.danger,
                                ),
                              );
                              return;
                            }
                            _pageController.nextPage(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
                            );
                          } else {
                            _saveProfile();
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                        child: authProvider.isLoading
                            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : Text(
                                _currentPageIndex < 3
                                    ? 'Lanjut'
                                    : (widget.fromRegistration ? 'Simpan & Lihat Rekomendasi' : 'Simpan Perubahan'),
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _stepTitle(int index) {
    switch (index) {
      case 0:
        return 'Demografis';
      case 1:
        return 'Budget';
      case 2:
        return 'Fasilitas';
      case 3:
        return 'Aturan Kos';
      default:
        return '';
    }
  }
}
