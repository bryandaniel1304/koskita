import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/kos_provider.dart';
import '../widgets/error_state.dart';

class KosDetailScreen extends StatefulWidget {
  final int kosId;

  const KosDetailScreen({super.key, required this.kosId});

  @override
  State<KosDetailScreen> createState() => _KosDetailScreenState();
}

class _KosDetailScreenState extends State<KosDetailScreen> {
  final _pageController = PageController();
  int _currentImage = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<KosProvider>(context, listen: false).fetchKosDetail(widget.kosId);
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _toggleFavorite(bool currentFavorite) async {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final success = await kosProvider.rateKos(widget.kosId, isFavorite: !currentFavorite);
    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(!currentFavorite ? 'Ditambahkan ke favorit' : 'Dihapus dari favorit'),
          duration: const Duration(seconds: 1),
        ),
      );
    }
  }

  void _setRating(int rating) async {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final success = await kosProvider.rateKos(widget.kosId, rating: rating);
    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Terima kasih! Rekomendasimu sudah diperbarui berdasarkan rating ini.'),
          backgroundColor: const Color(0xFF10B981),
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  String _formatPrice(int price) {
    if (price >= 1000000) {
      return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt / bln';
    }
    return 'Rp $price / bln';
  }

  @override
  Widget build(BuildContext context) {
    final kosProvider = Provider.of<KosProvider>(context);
    final kos = kosProvider.currentKos;
    final interaction = kosProvider.currentInteraction;

    if (kosProvider.isLoading && kos == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (kos == null) {
      return Scaffold(
        appBar: AppBar(backgroundColor: Colors.white, elevation: 0.5),
        body: ErrorStateView(
          message: kosProvider.errorMessage ?? 'Kos tidak ditemukan.',
          onRetry: () => Provider.of<KosProvider>(context, listen: false).fetchKosDetail(widget.kosId),
        ),
      );
    }

    final isFavorite = interaction?.isFavorite ?? false;
    final userRating = interaction?.rating ?? 0;
    final images = kos.galleryUrls;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: CustomScrollView(
        slivers: [
          // Image Header / Galeri
          SliverAppBar(
            expandedHeight: 280,
            pinned: true,
            backgroundColor: Colors.white,
            foregroundColor: const Color(0xFF0F172A),
            elevation: 0.5,
            actions: [
              IconButton(
                icon: Icon(
                  isFavorite ? Icons.favorite_rounded : Icons.favorite_outline_rounded,
                  color: isFavorite ? const Color(0xFFF43F5E) : const Color(0xFF0F172A),
                  size: 28,
                ),
                onPressed: () => _toggleFavorite(isFavorite),
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: images.isEmpty
                  ? Container(color: Colors.grey[300], child: const Icon(Icons.image, size: 60, color: Colors.grey))
                  : Stack(
                      fit: StackFit.expand,
                      children: [
                        PageView.builder(
                          controller: _pageController,
                          itemCount: images.length,
                          onPageChanged: (i) => setState(() => _currentImage = i),
                          itemBuilder: (context, index) => CachedNetworkImage(
                            imageUrl: images[index],
                            fit: BoxFit.cover,
                            placeholder: (context, url) => Container(color: Colors.grey[200]),
                            errorWidget: (context, url, error) => Container(
                              color: Colors.grey[300],
                              child: const Icon(Icons.image, size: 60, color: Colors.grey),
                            ),
                          ),
                        ),
                        if (images.length > 1)
                          Positioned(
                            bottom: 12,
                            left: 0,
                            right: 0,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: List.generate(images.length, (i) {
                                return Container(
                                  width: 6,
                                  height: 6,
                                  margin: const EdgeInsets.symmetric(horizontal: 3),
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: Colors.white.withValues(alpha: i == _currentImage ? 1 : 0.4),
                                  ),
                                );
                              }),
                            ),
                          ),
                      ],
                    ),
            ),
          ),

          // Detail Content
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title Block
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              kos.name,
                              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                            ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEEF2F6),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    kos.genderType.toUpperCase(),
                                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                const Icon(Icons.location_on, size: 14, color: Colors.grey),
                                const SizedBox(width: 2),
                                Text(kos.location, style: const TextStyle(color: Colors.grey, fontSize: 13)),
                                const SizedBox(width: 12),
                                const Icon(Icons.directions_walk, size: 14, color: Colors.grey),
                                const SizedBox(width: 2),
                                Text('${kos.distanceToCampus} km ke kampus', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Price Block
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Harga Sewa',
                          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                        ),
                        Text(
                          _formatPrice(kos.price),
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF4F46E5),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Booking CTA
                  Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      gradient: const LinearGradient(colors: [Color(0xFF6366F1), Color(0xFF4F46E5)]),
                    ),
                    child: ElevatedButton.icon(
                      onPressed: () => context.push('/booking/new', extra: kos),
                      icon: const Icon(Icons.calendar_month_rounded, color: Colors.white),
                      label: const Text('Ajukan Booking', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Rating Block
                  const Text(
                    'Bagaimana Menurutmu?',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 8),
                  Card(
                    elevation: 1,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          const Text(
                            'Kasih rating kalau kamu tertarik dengan kos ini — rekomendasi berikutnya akan makin sesuai seleramu.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: List.generate(5, (index) {
                              final starIndex = index + 1;
                              return IconButton(
                                icon: Icon(
                                  starIndex <= userRating ? Icons.star_rounded : Icons.star_outline_rounded,
                                  color: Colors.amber,
                                  size: 36,
                                ),
                                onPressed: () => _setRating(starIndex),
                              );
                            }),
                          ),
                          if (userRating > 0)
                            Text(
                              'Rating kamu: $userRating Bintang',
                              style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                            ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Description
                  const Text(
                    'Deskripsi Kos',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    kos.description,
                    style: const TextStyle(fontSize: 14, color: Color(0xFF334155), height: 1.6),
                  ),
                  const SizedBox(height: 24),

                  // Facilities
                  const Text(
                    'Fasilitas Tersedia',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 12),
                  if (kos.facilities.isEmpty)
                    const Text('Tidak ada info fasilitas', style: TextStyle(color: Colors.grey))
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: kos.facilities.map((fac) {
                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.check_circle_outline, size: 16, color: Color(0xFF6366F1)),
                              const SizedBox(width: 6),
                              Text(fac.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                            ],
                          ),
                        );
                      }).toList(),
                    ),
                  const SizedBox(height: 24),

                  // Rules
                  const Text(
                    'Aturan Kos',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 12),
                  if (kos.rules.isEmpty)
                    const Text('Tidak ada aturan khusus', style: TextStyle(color: Colors.grey))
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: kos.rules.map((rule) {
                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.info_outline_rounded, size: 16, color: Color(0xFFF43F5E)),
                              const SizedBox(width: 6),
                              Text(rule.name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
                            ],
                          ),
                        );
                      }).toList(),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
