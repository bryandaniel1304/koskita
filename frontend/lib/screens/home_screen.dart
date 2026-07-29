import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';
import '../models/kos.dart';
import '../widgets/error_state.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _searchController = TextEditingController();
  String? _selectedGender;
  String? _selectedLocation;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
    });
  }

  void _loadData() {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    kosProvider.fetchKoses();
    kosProvider.fetchRecommendations();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  String _formatPrice(int price) {
    if (price >= 1000000) {
      return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt / bln';
    }
    return 'Rp $price / bln';
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final kosProvider = Provider.of<KosProvider>(context);

    final user = authProvider.user;
    final showEmptyState = !kosProvider.isLoading &&
        kosProvider.koses.isEmpty &&
        kosProvider.recommendations.isEmpty &&
        kosProvider.errorMessage != null;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('KOSKITA', style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1.5)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
      ),
      body: kosProvider.isLoading && kosProvider.koses.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : showEmptyState
              ? ErrorStateView(message: kosProvider.errorMessage!, onRetry: _loadData)
              : RefreshIndicator(
                  onRefresh: () async {
                    _loadData();
                  },
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Welcome & Location Header
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Halo, ${user?.name ?? "Pengguna"}!',
                                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    const Icon(Icons.location_on, size: 14, color: Color(0xFF6366F1)),
                                    const SizedBox(width: 4),
                                    Text(
                                      user?.profile?.preferredLocation ?? 'Lokasi belum diset',
                                      style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),

                        // Banner rekomendasi otomatis (tidak ada tombol, langsung tampil)
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(16),
                            gradient: kosProvider.isColdStart
                                ? const LinearGradient(
                                    colors: [Color(0xFF6366F1), Color(0xFF4F46E5)],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  )
                                : const LinearGradient(
                                    colors: [Color(0xFF10B981), Color(0xFF059669)],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  ),
                            boxShadow: [
                              BoxShadow(
                                color: (kosProvider.isColdStart ? const Color(0xFF6366F1) : const Color(0xFF10B981)).withValues(alpha: 0.3),
                                blurRadius: 10,
                                offset: const Offset(0, 4),
                              )
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    kosProvider.isColdStart ? Icons.auto_awesome_rounded : Icons.flash_on_rounded,
                                    color: Colors.white,
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    kosProvider.isColdStart ? 'Rekomendasi Untukmu' : 'Rekomendasi Makin Akurat',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w900,
                                      fontSize: 14,
                                      letterSpacing: 0.5,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                kosProvider.isColdStart
                                    ? 'Berdasarkan preferensimu. Beri rating kos yang kamu suka supaya rekomendasi makin akurat.'
                                    : 'Rekomendasi ini disesuaikan dengan ${kosProvider.ratingCount} rating yang sudah kamu berikan.',
                                style: const TextStyle(color: Colors.white, fontSize: 12, height: 1.4),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // SECTION 1: Recommended Kos
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Rekomendasi Terbaik Untukmu',
                              style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                            ),
                            if (kosProvider.recommendations.isEmpty)
                              const Text('Tidak ada data', style: TextStyle(color: Colors.grey))
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (kosProvider.recommendations.isEmpty)
                          Container(
                            height: 150,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: const Text('Silakan lengkapi profil preferensi Anda.', style: TextStyle(color: Colors.grey)),
                          )
                        else
                          SizedBox(
                            height: 260,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              itemCount: kosProvider.recommendations.length,
                              itemBuilder: (context, index) {
                                final rec = kosProvider.recommendations[index];
                                final kos = rec['kos'] as Kos;
                                final match = rec['match_percentage'] as int;

                                return GestureDetector(
                                  onTap: () => context.push('/kos/${kos.id}'),
                                  child: Container(
                                    width: 200,
                                    margin: const EdgeInsets.only(right: 16),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(16),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withValues(alpha: 0.05),
                                          blurRadius: 8,
                                          offset: const Offset(0, 2),
                                        )
                                      ],
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        // Image & Match Badge
                                        Stack(
                                          children: [
                                            ClipRRect(
                                              borderRadius: const BorderRadius.only(
                                                topLeft: Radius.circular(16),
                                                topRight: Radius.circular(16),
                                              ),
                                              child: CachedNetworkImage(
                                                imageUrl: kos.coverImage,
                                                height: 120,
                                                width: double.infinity,
                                                fit: BoxFit.cover,
                                                placeholder: (context, url) => Container(height: 120, color: Colors.grey[200]),
                                                errorWidget: (context, url, error) => Container(
                                                  height: 120,
                                                  color: Colors.grey[300],
                                                  child: const Icon(Icons.image, color: Colors.grey),
                                                ),
                                              ),
                                            ),
                                            Positioned(
                                              top: 8,
                                              right: 8,
                                              child: Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                decoration: BoxDecoration(
                                                  color: kosProvider.isColdStart ? const Color(0xFF6366F1) : const Color(0xFF10B981),
                                                  borderRadius: BorderRadius.circular(12),
                                                ),
                                                child: Text(
                                                  '$match% Cocok',
                                                  style: const TextStyle(
                                                    color: Colors.white,
                                                    fontSize: 10,
                                                    fontWeight: FontWeight.bold,
                                                  ),
                                                ),
                                              ),
                                            ),
                                            Positioned(
                                              bottom: 8,
                                              left: 8,
                                              child: Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                decoration: BoxDecoration(
                                                  color: Colors.black.withValues(alpha: 0.6),
                                                  borderRadius: BorderRadius.circular(6),
                                                ),
                                                child: Text(
                                                  kos.genderType.toUpperCase(),
                                                  style: const TextStyle(
                                                    color: Colors.white,
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.bold,
                                                  ),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        // Details
                                        Padding(
                                          padding: const EdgeInsets.all(12),
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                kos.name,
                                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                              ),
                                              const SizedBox(height: 4),
                                              Row(
                                                children: [
                                                  const Icon(Icons.location_on, size: 12, color: Colors.grey),
                                                  const SizedBox(width: 2),
                                                  Text(kos.location, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                                  const SizedBox(width: 8),
                                                  const Icon(Icons.directions_walk, size: 12, color: Colors.grey),
                                                  const SizedBox(width: 2),
                                                  Text('${kos.distanceToCampus} km', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                                ],
                                              ),
                                              const SizedBox(height: 12),
                                              Text(
                                                _formatPrice(kos.price),
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 13,
                                                  color: Color(0xFF4F46E5),
                                                ),
                                              ),
                                            ],
                                          ),
                                        )
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        const SizedBox(height: 28),

                        // SECTION 2: Filter & Search All Kos
                        const Text(
                          'Semua Kos Tersedia',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 12),

                        // Search & Filters Row
                        TextField(
                          controller: _searchController,
                          decoration: InputDecoration(
                            hintText: 'Cari nama kos atau lokasi...',
                            prefixIcon: const Icon(Icons.search),
                            suffixIcon: IconButton(
                              icon: const Icon(Icons.clear),
                              onPressed: () {
                                _searchController.clear();
                                kosProvider.fetchKoses(
                                  genderType: _selectedGender,
                                  location: _selectedLocation,
                                );
                              },
                            ),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                            ),
                            filled: true,
                            fillColor: Colors.white,
                          ),
                          onSubmitted: (value) {
                            kosProvider.fetchKoses(
                              search: value,
                              genderType: _selectedGender,
                              location: _selectedLocation,
                            );
                          },
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            // Gender Filter
                            Expanded(
                              child: DropdownButtonFormField<String>(
                                initialValue: _selectedGender,
                                decoration: InputDecoration(
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                                  filled: true,
                                  fillColor: Colors.white,
                                  hintText: 'Semua Tipe',
                                ),
                                items: const [
                                  DropdownMenuItem(value: null, child: Text('Semua Tipe')),
                                  DropdownMenuItem(value: 'putra', child: Text('Putra')),
                                  DropdownMenuItem(value: 'putri', child: Text('Putri')),
                                  DropdownMenuItem(value: 'campur', child: Text('Campur')),
                                ],
                                onChanged: (val) {
                                  setState(() => _selectedGender = val);
                                  kosProvider.fetchKoses(
                                    search: _searchController.text,
                                    genderType: val,
                                    location: _selectedLocation,
                                  );
                                },
                              ),
                            ),
                            const SizedBox(width: 8),
                            // Location Filter
                            Expanded(
                              child: DropdownButtonFormField<String>(
                                initialValue: _selectedLocation,
                                decoration: InputDecoration(
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                                  filled: true,
                                  fillColor: Colors.white,
                                  hintText: 'Semua Area',
                                ),
                                items: const [
                                  DropdownMenuItem(value: null, child: Text('Semua Area')),
                                  DropdownMenuItem(value: 'Karawaci', child: Text('Karawaci')),
                                  DropdownMenuItem(value: 'BSD', child: Text('BSD')),
                                  DropdownMenuItem(value: 'Serpong', child: Text('Serpong')),
                                ],
                                onChanged: (val) {
                                  setState(() => _selectedLocation = val);
                                  kosProvider.fetchKoses(
                                    search: _searchController.text,
                                    genderType: _selectedGender,
                                    location: val,
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),

                        // Vertical Kos List
                        if (kosProvider.koses.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 24),
                            child: Text(
                              'Tidak ada kos yang cocok dengan pencarianmu.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: Colors.grey[600]),
                            ),
                          )
                        else
                          ListView.builder(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: kosProvider.koses.length,
                            itemBuilder: (context, index) {
                              final kos = kosProvider.koses[index];
                              return GestureDetector(
                                onTap: () => context.push('/kos/${kos.id}'),
                                child: Container(
                                  margin: const EdgeInsets.only(bottom: 16),
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(16),
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withValues(alpha: 0.02),
                                        blurRadius: 6,
                                        offset: const Offset(0, 2),
                                      )
                                    ],
                                  ),
                                  child: Row(
                                    children: [
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(12),
                                        child: CachedNetworkImage(
                                          imageUrl: kos.coverImage,
                                          width: 90,
                                          height: 90,
                                          fit: BoxFit.cover,
                                          placeholder: (context, url) => Container(width: 90, height: 90, color: Colors.grey[200]),
                                          errorWidget: (context, url, error) => Container(
                                            width: 90,
                                            height: 90,
                                            color: Colors.grey[300],
                                            child: const Icon(Icons.image, color: Colors.grey),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 16),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFF1F5F9),
                                                borderRadius: BorderRadius.circular(4),
                                              ),
                                              child: Text(
                                                kos.genderType.toUpperCase(),
                                                style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                                              ),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              kos.name,
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A)),
                                            ),
                                            const SizedBox(height: 4),
                                            Row(
                                              children: [
                                                const Icon(Icons.location_on, size: 12, color: Colors.grey),
                                                const SizedBox(width: 2),
                                                Text(kos.location, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                                const SizedBox(width: 8),
                                                const Icon(Icons.directions_walk, size: 12, color: Colors.grey),
                                                const SizedBox(width: 2),
                                                Text('${kos.distanceToCampus} km', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                              ],
                                            ),
                                            const SizedBox(height: 8),
                                            Text(
                                              _formatPrice(kos.price),
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF4F46E5)),
                                            ),
                                          ],
                                        ),
                                      )
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
