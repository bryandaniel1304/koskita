import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/kos_provider.dart';
import '../widgets/error_state.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<KosProvider>(context, listen: false).fetchFavorites();
    });
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

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Favorit Saya', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0.5,
      ),
      body: RefreshIndicator(
        onRefresh: () => kosProvider.fetchFavorites(),
        child: Builder(builder: (context) {
          if (kosProvider.isFavoritesLoading && kosProvider.favorites.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }
          if (kosProvider.favoritesErrorMessage != null && kosProvider.favorites.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                ErrorStateView(
                  message: kosProvider.favoritesErrorMessage!,
                  onRetry: () => kosProvider.fetchFavorites(),
                ),
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
            padding: const EdgeInsets.all(20),
            itemCount: kosProvider.favorites.length,
            itemBuilder: (context, index) {
              final kos = kosProvider.favorites[index];
              return GestureDetector(
                onTap: () => context.push('/kos/${kos.id}'),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 6, offset: const Offset(0, 2)),
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
                            Text(kos.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                const Icon(Icons.location_on, size: 12, color: Colors.grey),
                                const SizedBox(width: 2),
                                Text(kos.location, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF4F46E5))),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        }),
      ),
    );
  }
}
