import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';

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
  String _gender = 'pria';
  String _occupation = 'mahasiswa';
  String _preferredLocation = 'Karawaci';
  
  RangeValues _budgetRange = const RangeValues(1000000, 3000000);

  final List<String> _allFacilities = ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'];
  final List<String> _preferredFacilities = [];

  final List<String> _allRules = ['Jam Malam', 'Tamu Boleh Menginap', 'Bawa Hewan', 'Merokok'];
  final List<String> _preferredRules = [];

  Future<void> _saveProfile() async {
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
          backgroundColor: Color(0xFFF43F5E),
        ),
      );
    }
  }

  String _formatRupiah(double value) {
    return 'Rp ${(value / 1000000).toStringAsFixed(1)} jt';
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Preferensi Pencarian Kos',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Welcome Card
            Card(
              elevation: 0,
              color: const Color(0xFFEEF2F6),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, color: Color(0xFF4F46E5), size: 30),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Kenalan Dulu, Yuk!',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A)),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Isi preferensimu supaya kami bisa langsung kasih rekomendasi kos yang paling cocok.',
                            style: TextStyle(fontSize: 13, color: const Color(0xFF0F172A).withValues(alpha: 0.6)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 1. Profil Demografis
            const Text(
              'Profil Demografis',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    // Gender
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Jenis Kelamin', style: TextStyle(fontWeight: FontWeight.w600)),
                        Row(
                          children: [
                            ChoiceChip(
                              label: const Text('Pria'),
                              selected: _gender == 'pria',
                              onSelected: (selected) {
                                if (selected) setState(() => _gender = 'pria');
                              },
                              selectedColor: const Color(0xFF6366F1),
                              labelStyle: TextStyle(
                                color: _gender == 'pria' ? Colors.white : Colors.black,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(width: 8),
                            ChoiceChip(
                              label: const Text('Wanita'),
                              selected: _gender == 'wanita',
                              onSelected: (selected) {
                                if (selected) setState(() => _gender = 'wanita');
                              },
                              selectedColor: const Color(0xFF6366F1),
                              labelStyle: TextStyle(
                                color: _gender == 'wanita' ? Colors.white : Colors.black,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    // Pekerjaan
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Pekerjaan', style: TextStyle(fontWeight: FontWeight.w600)),
                        Row(
                          children: [
                            ChoiceChip(
                              label: const Text('Mahasiswa'),
                              selected: _occupation == 'mahasiswa',
                              onSelected: (selected) {
                                if (selected) setState(() => _occupation = 'mahasiswa');
                              },
                              selectedColor: const Color(0xFF6366F1),
                              labelStyle: TextStyle(
                                color: _occupation == 'mahasiswa' ? Colors.white : Colors.black,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(width: 8),
                            ChoiceChip(
                              label: const Text('Pekerja'),
                              selected: _occupation == 'pekerja',
                              onSelected: (selected) {
                                if (selected) setState(() => _occupation = 'pekerja');
                              },
                              selectedColor: const Color(0xFF6366F1),
                              labelStyle: TextStyle(
                                color: _occupation == 'pekerja' ? Colors.white : Colors.black,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    // Lokasi Preferensi
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Area Kampus', style: TextStyle(fontWeight: FontWeight.w600)),
                        DropdownButton<String>(
                          value: _preferredLocation,
                          onChanged: (String? newValue) {
                            if (newValue != null) {
                              setState(() => _preferredLocation = newValue);
                            }
                          },
                          underline: Container(
                            height: 1,
                            color: const Color(0xFF6366F1),
                          ),
                          items: <String>['Karawaci', 'BSD', 'Serpong']
                              .map<DropdownMenuItem<String>>((String value) {
                            return DropdownMenuItem<String>(
                              value: value,
                              child: Text(value, style: const TextStyle(fontWeight: FontWeight.w500)),
                            );
                          }).toList(),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 2. Anggaran Bulanan
            const Text(
              'Anggaran Bulanan',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Rentang Budget', style: TextStyle(fontWeight: FontWeight.w600)),
                        Text(
                          '${_formatRupiah(_budgetRange.start)} - ${_formatRupiah(_budgetRange.end)}',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF4F46E5),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    RangeSlider(
                      values: _budgetRange,
                      min: 500000,
                      max: 6000000,
                      divisions: 11,
                      activeColor: const Color(0xFF6366F1),
                      inactiveColor: const Color(0xFFE2E8F0),
                      onChanged: (values) {
                        setState(() {
                          _budgetRange = values;
                        });
                      },
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 3. Fasilitas yang Diinginkan
            const Text(
              'Fasilitas Utama',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _allFacilities.map((facility) {
                final isSelected = _preferredFacilities.contains(facility);
                return FilterChip(
                  label: Text(facility),
                  selected: isSelected,
                  selectedColor: const Color(0xFF6366F1).withValues(alpha: 0.2),
                  checkmarkColor: const Color(0xFF4F46E5),
                  labelStyle: TextStyle(
                    color: isSelected ? const Color(0xFF4F46E5) : Colors.black,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  ),
                  onSelected: (selected) {
                    setState(() {
                      if (selected) {
                        _preferredFacilities.add(facility);
                      } else {
                        _preferredFacilities.remove(facility);
                      }
                    });
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 24),

            // 4. Aturan Kos
            const Text(
              'Aturan Kos Toleransi',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _allRules.map((rule) {
                final isSelected = _preferredRules.contains(rule);
                return FilterChip(
                  label: Text(rule),
                  selected: isSelected,
                  selectedColor: const Color(0xFF6366F1).withValues(alpha: 0.2),
                  checkmarkColor: const Color(0xFF4F46E5),
                  labelStyle: TextStyle(
                    color: isSelected ? const Color(0xFF4F46E5) : Colors.black,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  ),
                  onSelected: (selected) {
                    setState(() {
                      if (selected) {
                        _preferredRules.add(rule);
                      } else {
                        _preferredRules.remove(rule);
                      }
                    });
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 36),

            // Save Button
            authProvider.isLoading
                ? const Center(child: CircularProgressIndicator())
                : Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      gradient: const LinearGradient(
                        colors: [Color(0xFF6366F1), Color(0xFF4F46E5)],
                      ),
                    ),
                    child: ElevatedButton(
                      onPressed: _saveProfile,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Simpan & Lihat Rekomendasi',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
                    ),
                  ),
          ],
        ),
      ),
    );
  }
}
