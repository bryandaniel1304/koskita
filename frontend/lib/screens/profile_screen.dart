import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  String _formatRupiah(int value) {
    return 'Rp ${(value / 1000000).toStringAsFixed(1)} jt';
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final user = authProvider.user;
    final profile = user?.profile;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Profil Pengguna', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Keluar',
            onPressed: () async {
              await authProvider.logout();
              if (context.mounted) context.go('/login');
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // User Basic Info Card
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: const Color(0xFF6366F1).withValues(alpha: 0.1),
                      child: const Icon(Icons.person_rounded, size: 40, color: Color(0xFF4F46E5)),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      user?.name ?? 'Nama Pengguna',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user?.email ?? 'email@example.com',
                      style: TextStyle(fontSize: 14, color: Colors.grey[600]),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Profile Preferences Card
            const Text(
              'Preferensi Kos Anda',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: profile == null
                    ? const Center(child: Text('Profil belum dikonfigurasi.'))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildProfileRow('Jenis Kelamin', profile.gender.toUpperCase()),
                          const Divider(height: 24),
                          _buildProfileRow('Pekerjaan', profile.occupation.toUpperCase()),
                          const Divider(height: 24),
                          _buildProfileRow('Area Kampus', profile.preferredLocation),
                          const Divider(height: 24),
                          _buildProfileRow(
                            'Rentang Anggaran',
                            '${_formatRupiah(profile.budgetMin)} - ${_formatRupiah(profile.budgetMax)}',
                          ),
                          const Divider(height: 24),
                          const Text(
                            'Fasilitas yang Diinginkan:',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Color(0xFF64748B)),
                          ),
                          const SizedBox(height: 8),
                          profile.preferredFacilities.isEmpty
                              ? const Text('Tidak ada fasilitas spesifik', style: TextStyle(fontSize: 13, color: Colors.grey))
                              : Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: profile.preferredFacilities.map((f) {
                                    return Chip(
                                      label: Text(f, style: const TextStyle(fontSize: 11)),
                                      padding: EdgeInsets.zero,
                                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                    );
                                  }).toList(),
                                ),
                          const Divider(height: 24),
                          const Text(
                            'Aturan yang Ditoleransi:',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Color(0xFF64748B)),
                          ),
                          const SizedBox(height: 8),
                          profile.preferredRules.isEmpty
                              ? const Text('Tidak ada aturan spesifik', style: TextStyle(fontSize: 13, color: Colors.grey))
                              : Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: profile.preferredRules.map((r) {
                                    return Chip(
                                      label: Text(r, style: const TextStyle(fontSize: 11)),
                                      padding: EdgeInsets.zero,
                                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                    );
                                  }).toList(),
                                ),
                        ],
                      ),
              ),
            ),
            const SizedBox(height: 24),

            // Actions Block
            const Text(
              'Pengaturan Akun',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 12),
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Edit Preferences
                    ElevatedButton.icon(
                      icon: const Icon(Icons.edit_rounded, size: 18),
                      label: const Text('Edit Profil Preferensi'),
                      onPressed: () => context.push('/onboarding'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: const Color(0xFF4F46E5),
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: Color(0xFF6366F1), width: 1.5),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    
                    // Reset Interactions (Cold-Start trigger)
                    ElevatedButton.icon(
                      icon: const Icon(Icons.refresh_rounded, size: 18),
                      label: const Text('Reset Riwayat Rating & Favorit'),
                      onPressed: () async {
                        final success = await authProvider.resetInteractions();
                        if (success && context.mounted) {
                          await kosProvider.fetchRecommendations();
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Riwayat rating & favorit direset. Rekomendasi akan dihitung ulang dari awal.'),
                              backgroundColor: Color(0xFF6366F1),
                            ),
                          );
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFEEF2F6),
                        foregroundColor: const Color(0xFF4F46E5),
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileRow(String title, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
      ],
    );
  }
}
