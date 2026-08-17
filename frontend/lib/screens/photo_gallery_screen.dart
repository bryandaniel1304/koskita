import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';

/// Galeri foto layar penuh -- dibuka dari detail kos (ketuk foto mana pun,
/// atau tombol "Lihat semua foto") supaya calon penyewa bisa lihat SEMUA
/// foto kos dengan jelas: swipe antar foto, cubit untuk zoom, dan strip
/// thumbnail di bawah untuk lompat langsung ke foto tertentu.
class PhotoGalleryScreen extends StatefulWidget {
  final List<String> images;
  final int initialIndex;
  final String? heroTagPrefix;

  const PhotoGalleryScreen({
    super.key,
    required this.images,
    this.initialIndex = 0,
    this.heroTagPrefix,
  });

  @override
  State<PhotoGalleryScreen> createState() => _PhotoGalleryScreenState();
}

class _PhotoGalleryScreenState extends State<PhotoGalleryScreen> {
  late final PageController _pageController;
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex.clamp(0, widget.images.length - 1);
    _pageController = PageController(initialPage: _currentIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _jumpTo(int index) {
    _pageController.animateToPage(index, duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: Colors.white, size: 26),
                    tooltip: 'Tutup galeri',
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                    child: Text(
                      '${_currentIndex + 1} / ${widget.images.length}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13),
                    ),
                  ),
                  const Spacer(),
                  const SizedBox(width: 42), // seimbangkan tombol close di kiri
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                itemCount: widget.images.length,
                onPageChanged: (i) => setState(() => _currentIndex = i),
                itemBuilder: (context, index) {
                  final url = widget.images[index];
                  return InteractiveViewer(
                    minScale: 1,
                    maxScale: 4,
                    child: Center(
                      child: Hero(
                        tag: widget.heroTagPrefix != null ? '${widget.heroTagPrefix}_$index' : 'gallery_$index',
                        child: CachedNetworkImage(
                          imageUrl: url,
                          fit: BoxFit.contain,
                          placeholder: (context, url) => const Center(child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2)),
                          errorWidget: (context, url, error) => const Icon(Icons.broken_image_outlined, color: Colors.white38, size: 48),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            if (widget.images.length > 1)
              SizedBox(
                height: 66,
                child: ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  scrollDirection: Axis.horizontal,
                  itemCount: widget.images.length,
                  itemBuilder: (context, index) {
                    final active = index == _currentIndex;
                    return GestureDetector(
                      onTap: () => _jumpTo(index),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        width: 50,
                        margin: const EdgeInsets.only(right: 8),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: active ? Colors.white : Colors.transparent, width: 2),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Opacity(
                            opacity: active ? 1 : 0.5,
                            child: CachedNetworkImage(imageUrl: widget.images[index], fit: BoxFit.cover, width: 50, height: 50),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
