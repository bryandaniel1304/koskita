import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/kos_provider.dart';
import '../models/kos.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../widgets/premium_button.dart';
import '../config/app_theme.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  bool _compareMode = false;
  final Set<int> _selectedIds = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<KosProvider>(context, listen: false).fetchFavorites();
    });
  }

  String _formatPrice(int price) {
    if (price >= 1000000) {
      return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln';
    }
    return 'Rp $price/bln';
  }

  void _toggleCompareMode() {
    setState(() {
      _compareMode = !_compareMode;
      if (!_compareMode) _selectedIds.clear();
    });
  }

  void _openComparison(List<Kos> favorites) {
    final selected = favorites.where((k) => _selectedIds.contains(k.id)).toList();
    context.push('/compare', extra: selected);
  }

  @override
  Widget build(BuildContext context) {
    final kosProvider = Provider.of<KosProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Favorit Saya'),
        actions: [
          if (kosProvider.favorites.length >= 2)
            TextButton(
              onPressed: _toggleCompareMode,
              child: Text(_compareMode ? 'Batal' : 'Bandingkan'),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => kosProvider.fetchFavorites(),
        child: Builder(builder: (context) {
          if (kosProvider.isFavoritesLoading && kosProvider.favorites.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(20),
              children: const [SkeletonKosCard(), SizedBox(height: 14), SkeletonKosCard(), SizedBox(height: 14), SkeletonKosCard()],
            );
          }
          if (kosProvider.favoritesErrorMessage != null && kosProvider.favorites.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(message: kosProvider.favoritesErrorMessage!, onRetry: () => kosProvider.fetchFavorites()),
              ],
            );
          }
          if (kosProvider.favorites.isEmpty) {
            return ListView(
              children: const [
                SizedBox(height: 80),
                ErrorStateView.empty(
                  message: 'Belum ada kos favorit.\nKetuk ikon hati di halaman detail kos untuk menyimpannya di sini.',
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
            itemCount: kosProvider.favorites.length,
            itemBuilder: (context, index) {
              final kos = kosProvider.favorites[index];
              final selected = _selectedIds.contains(kos.id);
              return GestureDetector(
                onTap: _compareMode
                    ? () {
                        setState(() {
                          // Tidak ada batas jumlah lagi -- CompareScreen sudah
                          // bisa discroll ke samping berapa pun banyaknya
                          // kos yang dipilih.
                          if (selected) {
                            _selectedIds.remove(kos.id);
                          } else {
                            _selectedIds.add(kos.id);
                          }
                        });
                      }
                    : () => context.push('/kos/${kos.id}'),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(18),
                    border: selected ? Border.all(color: AppTheme.primary, width: 1.6) : null,
                    boxShadow: AppTheme.softShadow(opacity: 0.05),
                  ),
                  child: Row(
                    children: [
                      if (_compareMode) ...[
                        Icon(
                          selected ? Icons.check_circle_rounded : Icons.circle_outlined,
                          color: selected ? AppTheme.primary : AppTheme.muted,
                        ),
                        const SizedBox(width: 10),
                      ],
                      ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: CachedNetworkImage(
                          imageUrl: kos.coverImage,
                          width: 90,
                          height: 90,
                          fit: BoxFit.cover,
                          placeholder: (context, url) => const SkeletonBox(width: 90, height: 90),
                          errorWidget: (context, url, error) => Container(width: 90, height: 90, color: Colors.grey[300], child: const Icon(Icons.image, color: Colors.grey)),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(kos.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                            const SizedBox(height: 5),
                            Row(
                              children: [
                                const Icon(Icons.location_on_rounded, size: 12, color: AppTheme.muted),
                                const SizedBox(width: 2),
                                Text(kos.location, style: Theme.of(context).textTheme.bodySmall),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: AppTheme.primary)),
                          ],
                        ),
                      ),
                      if (!_compareMode) Icon(Icons.favorite_rounded, color: AppTheme.danger.withValues(alpha: 0.85), size: 20),
                    ],
                  ),
                ),
              ).animate(delay: (index.clamp(0, 6) * 45).ms).fadeIn(duration: 260.ms);
            },
          );
        }),
      ),
      bottomNavigationBar: _compareMode && _selectedIds.length >= 2
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: PremiumButton(
                  label: 'Bandingkan ${_selectedIds.length} Kos',
                  icon: Icons.compare_arrows_rounded,
                  onPressed: () => _openComparison(kosProvider.favorites),
                ),
              ),
            )
          : null,
    );
  }
}
