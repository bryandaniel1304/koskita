import 'dart:io';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:share_plus/share_plus.dart';
import 'package:screen_protector/screen_protector.dart';
import '../providers/kos_provider.dart';
import '../models/kos.dart';
import '../widgets/error_state.dart';
import '../widgets/kos_location_map.dart';
import '../widgets/premium_button.dart';
import '../widgets/skeleton_box.dart';
import '../config/app_theme.dart';
import '../utils/image_source_picker.dart';
import '../utils/haptics.dart';
import '../utils/undo_snackbar.dart';
import 'photo_gallery_screen.dart';

class KosDetailScreen extends StatefulWidget {
  final int kosId;

  const KosDetailScreen({super.key, required this.kosId});

  @override
  State<KosDetailScreen> createState() => _KosDetailScreenState();
}

class _KosDetailScreenState extends State<KosDetailScreen> {
  final _pageController = PageController();
  int _currentImage = 0;
  Future<List<Kos>>? _similarKosesFuture;
  int? _similarForKosId;
  // Dipasang cuma kalau kos ini punya QRIS (bukan seluruh layar detail
  // kos secara default) -- supaya pengguna tetap bisa screenshot listing
  // kos biasa buat dibagikan ke teman, tapi kode QRIS pembayaran tetap
  // terlindungi dari screenshot/rekam layar begitu ditampilkan.
  bool _screenshotProtected = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<KosProvider>(
        context,
        listen: false,
      ).fetchKosDetail(widget.kosId);
    });
  }

  void _shareKos(Kos kos) {
    Share.share(
      'Lihat kos ini di KosKita: ${kos.name} -- ${kos.location}, mulai ${_formatPrice(kos.price)}/bulan.\n'
      'https://koskita.example.com/kos/${kos.id}',
    );
  }

  @override
  void dispose() {
    _pageController.dispose();
    if (_screenshotProtected) ScreenProtector.preventScreenshotOff();
    super.dispose();
  }

  /// Nyalakan proteksi screenshot SEKALI begitu kos ini diketahui punya
  /// QRIS (baru terlihat setelah fetchKosDetail selesai) -- dipanggil dari
  /// build() tiap render, tapi guard `_screenshotProtected` memastikan
  /// hanya benar-benar memanggil channel native sekali per kunjungan layar.
  void _protectScreenshotIfHasQris(bool hasQris) {
    if (hasQris && !_screenshotProtected) {
      _screenshotProtected = true;
      ScreenProtector.preventScreenshotOn();
    }
  }

  void _toggleFavorite(bool currentFavorite) async {
    Haptics.light();
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final success = await kosProvider.rateKos(
      widget.kosId,
      isFavorite: !currentFavorite,
    );
    if (!success || !mounted) return;

    if (currentFavorite) {
      // Baru saja DIHAPUS dari favorit -- ini yang paling sering kepencet
      // tidak sengaja / berubah pikiran, kasih jalan keluar cepat lewat
      // "Batalkan" daripada harus cari lagi kos-nya dan favoritkan ulang.
      showUndoSnackBar(
        context,
        message: 'Dihapus dari favorit',
        onUndo: () => _toggleFavorite(false),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ditambahkan ke favorit'), duration: Duration(seconds: 1)),
      );
    }
  }

  void _openGallery(int initialIndex) {
    final images =
        Provider.of<KosProvider>(
          context,
          listen: false,
        ).currentKos?.galleryUrls ??
        [];
    if (images.isEmpty) return;
    Navigator.of(context).push(
      PageRouteBuilder(
        opaque: false,
        pageBuilder: (context, animation, secondaryAnimation) => FadeTransition(
          opacity: animation,
          child: PhotoGalleryScreen(
            images: images,
            initialIndex: initialIndex,
            heroTagPrefix: 'kos_${widget.kosId}_gallery',
          ),
        ),
      ),
    );
  }

  void _setRating(int rating) async {
    Haptics.selection();
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final success = await kosProvider.rateKos(widget.kosId, rating: rating);
    if (success && mounted) {
      Haptics.success();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(
            'Terima kasih! Rekomendasimu sudah diperbarui berdasarkan rating ini.',
          ),
          backgroundColor: AppTheme.success,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  Future<void> _showReviewDialog() async {
    int selectedRating = 5;
    final commentController = TextEditingController();
    XFile? selectedPhoto;

    final result = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Text('Tulis Ulasan'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Ulasan ini akan tampil ke pengguna lain.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(5, (i) {
                    final star = i + 1;
                    return IconButton(
                      icon: Icon(
                        star <= selectedRating
                            ? Icons.star_rounded
                            : Icons.star_outline_rounded,
                        color: AppTheme.warning,
                        size: 32,
                      ),
                      tooltip: 'Beri $star bintang',
                      onPressed: () =>
                          setDialogState(() => selectedRating = star),
                    );
                  }),
                ),
                TextField(
                  controller: commentController,
                  maxLines: 3,
                  maxLength: 500,
                  decoration: const InputDecoration(
                    hintText: 'Ceritakan pengalamanmu (opsional)...',
                  ),
                ),
                const SizedBox(height: 4),
                if (selectedPhoto != null)
                  Stack(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.file(
                          File(selectedPhoto!.path),
                          height: 120,
                          width: double.infinity,
                          fit: BoxFit.cover,
                        ),
                      ),
                      Positioned(
                        top: 4,
                        right: 4,
                        child: GestureDetector(
                          onTap: () =>
                              setDialogState(() => selectedPhoto = null),
                          child: Container(
                            padding: const EdgeInsets.all(3),
                            decoration: const BoxDecoration(
                              color: Colors.black54,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.close_rounded,
                              size: 14,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ),
                    ],
                  )
                else
                  OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await pickImageWithSourceChoice(context, title: 'Foto Ulasan');
                      if (picked != null) {
                        setDialogState(() => selectedPhoto = picked);
                      }
                    },
                    icon: const Icon(Icons.add_a_photo_outlined, size: 17),
                    label: const Text('Tambah Foto (opsional)'),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Kirim'),
            ),
          ],
        ),
      ),
    );

    if (result != true || !mounted) return;

    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final error = await kosProvider.submitReview(
      widget.kosId,
      rating: selectedRating,
      comment: commentController.text.trim(),
      photo: selectedPhoto,
    );
    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(error ?? 'Ulasan berhasil dikirim. Terima kasih!'),
        backgroundColor: error == null ? AppTheme.success : AppTheme.danger,
      ),
    );
  }

  static const _reportReasons = [
    'Foto/data tidak sesuai kondisi asli',
    'Kos tidak ada/sudah tidak beroperasi',
    'Harga yang diminta tidak sesuai listing',
    'Lainnya',
  ];

  Future<void> _showReportDialog(Kos kos) async {
    String? selectedReason;
    final detailsController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Text('Laporkan "${kos.name}"'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ..._reportReasons.map((reason) => InkWell(
                    onTap: () => setDialogState(() => selectedReason = reason),
                    borderRadius: BorderRadius.circular(10),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(
                        children: [
                          Icon(
                            selectedReason == reason ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
                            size: 18,
                            color: selectedReason == reason ? AppTheme.primary : AppTheme.muted,
                          ),
                          const SizedBox(width: 10),
                          Expanded(child: Text(reason, style: const TextStyle(fontSize: 13.5))),
                        ],
                      ),
                    ),
                  )),
              const SizedBox(height: 8),
              TextField(
                controller: detailsController,
                maxLines: 2,
                maxLength: 1000,
                decoration: const InputDecoration(hintText: 'Detail tambahan (opsional)'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Batal')),
            ElevatedButton(
              onPressed: selectedReason == null ? null : () => Navigator.pop(dialogContext, true),
              child: const Text('Kirim Laporan'),
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || selectedReason == null || !mounted) return;

    final provider = Provider.of<KosProvider>(context, listen: false);
    final success = await provider.reportKos(kos.id, selectedReason!, details: detailsController.text.trim());
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(success ? 'Laporan terkirim, terima kasih.' : 'Gagal mengirim laporan.')),
    );
  }

  String _formatPrice(int price) {
    if (price >= 1000000) {
      return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt';
    }
    return 'Rp $price';
  }

  Widget _circleIconButton({
    required IconData icon,
    required VoidCallback onTap,
    Color? color,
    String? tooltip,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.35),
        shape: BoxShape.circle,
      ),
      child: IconButton(
        icon: Icon(icon, color: color ?? Colors.white, size: 21),
        tooltip: tooltip,
        onPressed: onTap,
      ),
    );
  }

  Widget _pillTag(IconData icon, String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Theme.of(context).inputDecorationTheme.fillColor,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: AppTheme.muted),
          const SizedBox(width: 4),
          Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              color: AppTheme.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(text, style: Theme.of(context).textTheme.titleLarge),
  );

  @override
  Widget build(BuildContext context) {
    final kosProvider = Provider.of<KosProvider>(context);
    final kos = kosProvider.currentKos;
    final interaction = kosProvider.currentInteraction;
    _protectScreenshotIfHasQris(kosProvider.qrisUrl != null);

    if (kosProvider.isLoading && kos == null) {
      return Scaffold(
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: const [
              SkeletonBox(
                height: 260,
                borderRadius: BorderRadius.all(Radius.circular(20)),
              ),
              SizedBox(height: 20),
              SkeletonBox(height: 22, width: 220),
              SizedBox(height: 10),
              SkeletonBox(height: 14, width: 160),
              SizedBox(height: 24),
              SkeletonBox(
                height: 90,
                borderRadius: BorderRadius.all(Radius.circular(16)),
              ),
            ],
          ),
        ),
      );
    }

    if (kos == null) {
      return Scaffold(
        appBar: AppBar(elevation: 0.5),
        body: ErrorStateView(
          message: kosProvider.errorMessage ?? 'Kos tidak ditemukan.',
          onRetry: () => Provider.of<KosProvider>(
            context,
            listen: false,
          ).fetchKosDetail(widget.kosId),
        ),
      );
    }

    final isFavorite = interaction?.isFavorite ?? false;
    final userRating = interaction?.rating ?? 0;
    final images = kos.galleryUrls;

    // CATATAN PENTING: sengaja TIDAK pakai Scaffold.bottomNavigationBar di sini.
    // Di HP tertentu (ketemu di Samsung Android 16 / API 36 saat testing),
    // kombinasi Row+Column di dalam slot bottomNavigationBar bikin seluruh
    // body Scaffold gagal ke-render (layar jadi kosong/hitam, cuma sebagian
    // teks di bottomNavigationBar yang salah posisi yang muncul) -- tanpa
    // exception apapun di console, jadi kemungkinan besar quirk internal
    // Scaffold/engine di device tsb, bukan bug logika. Taruh bar harga+tombol
    // sebagai widget biasa di akhir Column dalam `body` (bukan slot terpisah)
    // menghindari jalur kode itu sepenuhnya dan sudah diverifikasi normal.
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      extendBodyBehindAppBar: true,
      body: Column(
        children: [
          Expanded(
            child: CustomScrollView(
              slivers: [
                SliverToBoxAdapter(
                  child: SizedBox(
                    height: 300,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        images.isEmpty
                            ? Container(
                                color: Colors.grey[300],
                                child: const Icon(
                                  Icons.image,
                                  size: 60,
                                  color: Colors.grey,
                                ),
                              )
                            : PageView.builder(
                                controller: _pageController,
                                itemCount: images.length,
                                onPageChanged: (i) =>
                                    setState(() => _currentImage = i),
                                itemBuilder: (context, index) => GestureDetector(
                                  onTap: () => _openGallery(index),
                                  child: Hero(
                                    tag: 'kos_${widget.kosId}_gallery_$index',
                                    child: CachedNetworkImage(
                                      imageUrl: images[index],
                                      fit: BoxFit.cover,
                                      placeholder: (context, url) =>
                                          const SkeletonBox(
                                            height: 300,
                                            borderRadius: BorderRadius.zero,
                                          ),
                                      errorWidget: (context, url, error) =>
                                          Container(
                                            color: Colors.grey[300],
                                            child: const Icon(
                                              Icons.image,
                                              size: 60,
                                              color: Colors.grey,
                                            ),
                                          ),
                                    ),
                                  ),
                                ),
                              ),
                        // Scrim gradasi supaya tombol & indikator tetap kebaca di atas foto apapun.
                        const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Color(0x66000000),
                                Colors.transparent,
                                Colors.transparent,
                                Color(0x33000000),
                              ],
                              stops: [0, 0.25, 0.7, 1],
                            ),
                          ),
                        ),
                        if (images.length > 1)
                          Positioned(
                            bottom: 14,
                            left: 0,
                            right: 0,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: List.generate(images.length, (i) {
                                final active = i == _currentImage;
                                return AnimatedContainer(
                                  duration: const Duration(milliseconds: 200),
                                  width: active ? 18 : 6,
                                  height: 6,
                                  margin: const EdgeInsets.symmetric(
                                    horizontal: 3,
                                  ),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(4),
                                    color: Colors.white.withValues(
                                      alpha: active ? 1 : 0.5,
                                    ),
                                  ),
                                );
                              }),
                            ),
                          ),
                        if (images.isNotEmpty)
                          Positioned(
                            bottom: 14,
                            right: 14,
                            child: GestureDetector(
                              onTap: () => _openGallery(_currentImage),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.black.withValues(alpha: 0.45),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.photo_library_outlined,
                                      color: Colors.white,
                                      size: 14,
                                    ),
                                    const SizedBox(width: 5),
                                    Text(
                                      '${_currentImage + 1}/${images.length}',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 11.5,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        Positioned(
                          top: 0,
                          left: 0,
                          right: 0,
                          child: SafeArea(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 8,
                              ),
                              child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  _circleIconButton(
                                    icon: Icons.arrow_back_rounded,
                                    onTap: () => context.pop(),
                                    tooltip: 'Kembali',
                                  ),
                                  Row(
                                    children: [
                                      _circleIconButton(
                                        icon: Icons.share_rounded,
                                        onTap: () => _shareKos(kos),
                                        tooltip: 'Bagikan kos ini',
                                      ),
                                      const SizedBox(width: 8),
                                      _circleIconButton(
                                        icon: isFavorite
                                            ? Icons.favorite_rounded
                                            : Icons.favorite_outline_rounded,
                                        color: isFavorite
                                            ? AppTheme.danger
                                            : Colors.white,
                                        onTap: () =>
                                            _toggleFavorite(isFavorite),
                                        tooltip: isFavorite
                                            ? 'Hapus dari favorit'
                                            : 'Tambah ke favorit',
                                      ),
                                      const SizedBox(width: 8),
                                      _circleIconButton(
                                        icon: Icons.flag_outlined,
                                        onTap: () => _showReportDialog(kos),
                                        tooltip: 'Laporkan kos ini',
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 22, 20, 110),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (kosProvider.isShowingCachedKosDetail) ...[
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(color: AppTheme.warning.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                            child: const Row(
                              children: [
                                Icon(Icons.cloud_off_rounded, size: 15, color: Color(0xFFB45309)),
                                SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    'Menampilkan data tersimpan dari kunjungan terakhir -- info bisa saja sudah berubah.',
                                    style: TextStyle(fontSize: 11.5, color: Color(0xFF92400E), fontWeight: FontWeight.w600),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        Text(
                          kos.name,
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            _pillTag(
                              Icons.wc_rounded,
                              kos.genderType.toUpperCase(),
                            ),
                            _pillTag(Icons.location_on_rounded, kos.location),
                            _pillTag(
                              Icons.directions_walk_rounded,
                              '${kos.distanceToCampus} km ke kampus',
                            ),
                            if (kos.verifiedAt != null)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                                child: const Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.verified_rounded, size: 14, color: AppTheme.primary),
                                    SizedBox(width: 3),
                                    Text('Terverifikasi', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppTheme.primary)),
                                  ],
                                ),
                              ),
                            if (kos.averageReviewRating != null)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: AppTheme.warning.withValues(
                                    alpha: 0.12,
                                  ),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.star_rounded,
                                      size: 14,
                                      color: AppTheme.warning,
                                    ),
                                    const SizedBox(width: 3),
                                    Text(
                                      '${kos.averageReviewRating!.toStringAsFixed(1)} (${kos.reviewsCount})',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFFB45309),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                          ],
                        ),
                        if (kosProvider.currentRecommendation != null) ...[
                          const SizedBox(height: 16),
                          Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [
                                  AppTheme.primary.withValues(alpha: 0.08),
                                  AppTheme.primary.withValues(alpha: 0.02),
                                ],
                              ),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppTheme.primary.withValues(alpha: 0.15)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.auto_awesome_rounded, color: AppTheme.primary, size: 18),
                                    const SizedBox(width: 8),
                                    Text(
                                      '${kosProvider.currentRecommendation!['match_percentage']}% Cocok dengan Profilmu',
                                      style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 13.5),
                                    ),
                                  ],
                                ),
                                const Divider(height: 20),
                                ...((kosProvider.currentRecommendation!['explanation'] as List<dynamic>?) ?? [])
                                    .map((reason) => Padding(
                                          padding: const EdgeInsets.only(bottom: 6),
                                          child: Row(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              const Text('• ', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary)),
                                              Expanded(
                                                child: Text(
                                                  reason.toString(),
                                                  style: const TextStyle(fontSize: 12.5, height: 1.35),
                                                ),
                                              ),
                                            ],
                                          ),
                                        )),
                              ],
                            ),
                          ),
                        ],
                        if (kos.ownerName != null) ...[
                          const SizedBox(height: 10),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Row(
                                  children: [
                                    const Icon(
                                      Icons.storefront_rounded,
                                      size: 14,
                                      color: AppTheme.muted,
                                    ),
                                    const SizedBox(width: 4),
                                    Flexible(
                                      child: Text(
                                        'Dikelola oleh ${kos.ownerName}',
                                        style: Theme.of(context).textTheme.bodySmall,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    if (kos.ownerVerified) ...[
                                      const SizedBox(width: 4),
                                      const Icon(Icons.verified_rounded, size: 14, color: AppTheme.primary),
                                    ],
                                  ],
                                ),
                              ),
                              if (kos.ownerId != null)
                                TextButton.icon(
                                  onPressed: () => context.push(
                                    '/messages/${kos.ownerId}?kos_id=${kos.id}',
                                    extra: kos.ownerName,
                                  ),
                                  icon: const Icon(Icons.chat_bubble_outline_rounded, size: 16),
                                  label: const Text('Chat Pemilik', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
                                  style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), minimumSize: Size.zero),
                                ),
                            ],
                          ),
                          if (kos.ownerResponseBadge != null) ...[
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                const Icon(Icons.bolt_rounded, size: 14, color: AppTheme.success),
                                const SizedBox(width: 4),
                                Text(kos.ownerResponseBadge!, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.success)),
                              ],
                            ),
                          ],
                        ],
                        const SizedBox(height: 22),

                        // Ketersediaan kamar -- harga & CTA utama ada di bar bawah layar.
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 12,
                          ),
                          decoration: BoxDecoration(
                            color: kos.availableRooms > 0
                                ? AppTheme.success.withValues(alpha: 0.08)
                                : AppTheme.danger.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                Icons.door_front_door_rounded,
                                size: 18,
                                color: kos.availableRooms > 0
                                    ? AppTheme.success
                                    : AppTheme.danger,
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  kos.availableRooms > 0
                                      ? '${kos.availableRooms} kamar tersedia sekarang'
                                      : 'Semua kamar sedang penuh',
                                  style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 13,
                                    color: kos.availableRooms > 0
                                        ? const Color(0xFF15803D)
                                        : const Color(0xFFB91C1C),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (Provider.of<KosProvider>(context).qrisUrl != null) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: AppTheme.primary.withValues(alpha: 0.06),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Row(
                                  children: [
                                    Icon(Icons.qr_code_rounded, size: 16, color: AppTheme.primary),
                                    SizedBox(width: 6),
                                    Text('Kode QRIS Pemilik', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Bayar sewa langsung ke pemilik (KosKita tidak memproses pembayaran apa pun).',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                                const SizedBox(height: 10),
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: CachedNetworkImage(imageUrl: Provider.of<KosProvider>(context, listen: false).qrisUrl!, width: 180, height: 180, fit: BoxFit.contain),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (kos.availableRooms <= 0) ...[
                          const SizedBox(height: 10),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final provider = Provider.of<KosProvider>(context, listen: false);
                                final wasOnWaitlist = provider.onWaitlist;
                                final success = await provider.toggleWaitlist(kos.id);
                                if (!context.mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      !success
                                          ? 'Gagal memperbarui daftar tunggu.'
                                          : wasOnWaitlist
                                              ? 'Kamu keluar dari daftar tunggu.'
                                              : 'Kamu akan diberi tahu begitu kamar tersedia lagi.',
                                    ),
                                  ),
                                );
                              },
                              icon: Icon(
                                Provider.of<KosProvider>(context).onWaitlist ? Icons.notifications_off_outlined : Icons.notifications_active_outlined,
                                size: 18,
                              ),
                              label: Text(Provider.of<KosProvider>(context).onWaitlist ? 'Batalkan Daftar Tunggu' : 'Beri Tahu Saya Saat Tersedia'),
                            ),
                          ),
                        ],
                        const SizedBox(height: 22),

                        KosLocationMap(
                          locationName: kos.location,
                          latitude: kos.latitude,
                          longitude: kos.longitude,
                        ),
                        const SizedBox(height: 26),

                        _sectionTitle('Bagaimana Menurutmu?'),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(18),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                              color:
                                  Theme.of(context).dividerTheme.color ??
                                  Colors.transparent,
                            ),
                          ),
                          child: Column(
                            children: [
                              Text(
                                'Kasih rating kalau kamu tertarik dengan kos ini -- rekomendasi berikutnya akan makin sesuai seleramu.',
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: List.generate(5, (index) {
                                  final starIndex = index + 1;
                                  return IconButton(
                                    icon: Icon(
                                      starIndex <= userRating
                                          ? Icons.star_rounded
                                          : Icons.star_outline_rounded,
                                      color: AppTheme.warning,
                                      size: 34,
                                    ),
                                    tooltip: 'Beri rating $starIndex bintang',
                                    onPressed: () => _setRating(starIndex),
                                  );
                                }),
                              ),
                              if (userRating > 0)
                                Text(
                                  'Rating kamu: $userRating Bintang',
                                  // Warna adaptif tema -- AppTheme.ink yang
                                  // lama hampir hitam, tak kebaca di kartu
                                  // mode gelap.
                                  style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: Theme.of(context).textTheme.bodyLarge?.color,
                                    fontSize: 12.5,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 26),

                        if (kos.roomTypes.isNotEmpty) ...[
                          _sectionTitle('Tipe Kamar'),
                          ...kos.roomTypes.map((type) => Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: Theme.of(context).inputDecorationTheme.fillColor,
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(type.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                                          const SizedBox(height: 2),
                                          Text(
                                            type.totalRooms > 0 ? '${type.totalRooms} kamar' : 'Penuh',
                                            style: Theme.of(context).textTheme.bodySmall,
                                          ),
                                        ],
                                      ),
                                    ),
                                    Text(
                                      '${_formatPrice(type.price)}/bln',
                                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: AppTheme.primary),
                                    ),
                                  ],
                                ),
                              )),
                          const SizedBox(height: 16),
                        ],

                        _sectionTitle('Deskripsi Kos'),
                        Text(
                          kos.description,
                          style: Theme.of(context).textTheme.bodyLarge,
                        ),
                        const SizedBox(height: 26),

                        _sectionTitle('Fasilitas Tersedia'),
                        if (kos.facilities.isEmpty)
                          Text(
                            'Tidak ada info fasilitas',
                            style: Theme.of(context).textTheme.bodySmall,
                          )
                        else
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: kos.facilities.map((fac) {
                              return Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 9,
                                ),
                                decoration: BoxDecoration(
                                  color: Theme.of(
                                    context,
                                  ).inputDecorationTheme.fillColor,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.check_circle_rounded,
                                      size: 15,
                                      color: AppTheme.success,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      fac.name,
                                      style: const TextStyle(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                        const SizedBox(height: 26),

                        _sectionTitle('Aturan Kos'),
                        if (kos.rules.isEmpty)
                          Text(
                            'Tidak ada aturan khusus',
                            style: Theme.of(context).textTheme.bodySmall,
                          )
                        else
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: kos.rules.map((rule) {
                              return Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 9,
                                ),
                                decoration: BoxDecoration(
                                  color: Theme.of(
                                    context,
                                  ).inputDecorationTheme.fillColor,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.info_rounded,
                                      size: 15,
                                      color: AppTheme.danger,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      rule.name,
                                      style: const TextStyle(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                        const SizedBox(height: 26),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Ulasan Pengguna',
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                            // Tombol cuma tampil kalau memang boleh -- masa
                            // sewanya sudah selesai, atau sudah pernah
                            // menulis ulasan sebelumnya (boleh diedit).
                            // Bukan cuma ditolak setelah ditekan.
                            if (kos.canReview)
                              TextButton.icon(
                                onPressed: _showReviewDialog,
                                icon: const Icon(
                                  Icons.rate_review_outlined,
                                  size: 16,
                                ),
                                label: const Text('Tulis Ulasan'),
                              ),
                          ],
                        ),
                        if (!kos.canReview) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Ulasan cuma bisa ditulis setelah masa sewamu di kos ini selesai.',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                        const SizedBox(height: 4),
                        if (kos.reviewsCount > 0) ...[
                          _RatingBreakdownBars(kos: kos),
                          const SizedBox(height: 14),
                        ],
                        if (kos.reviews.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Text(
                              'Belum ada ulasan. Jadi yang pertama menulis ulasan!',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          )
                        else
                          ...kos.reviews.map((review) {
                            return Container(
                              margin: const EdgeInsets.only(bottom: 10),
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: Theme.of(context).cardColor,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color:
                                      Theme.of(context).dividerTheme.color ??
                                      Colors.transparent,
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        review.userName,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 13,
                                        ),
                                      ),
                                      Text(
                                        DateFormat(
                                          'd MMM yyyy',
                                          'id_ID',
                                        ).format(review.createdAt),
                                        style: Theme.of(
                                          context,
                                        ).textTheme.bodySmall,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: List.generate(
                                      5,
                                      (i) => Icon(
                                        i < review.rating
                                            ? Icons.star_rounded
                                            : Icons.star_outline_rounded,
                                        color: AppTheme.warning,
                                        size: 14,
                                      ),
                                    ),
                                  ),
                                  if (review.comment != null &&
                                      review.comment!.isNotEmpty) ...[
                                    const SizedBox(height: 6),
                                    Text(
                                      review.comment!,
                                      style: Theme.of(
                                        context,
                                      ).textTheme.bodyMedium,
                                    ),
                                  ],
                                  if (review.photoUrl != null) ...[
                                    const SizedBox(height: 8),
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(10),
                                      child: CachedNetworkImage(
                                        imageUrl: review.photoUrl!,
                                        height: 140,
                                        width: double.infinity,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                  ],
                                  if (review.ownerReply != null && review.ownerReply!.isNotEmpty) ...[
                                    const SizedBox(height: 10),
                                    Container(
                                      padding: const EdgeInsets.all(10),
                                      decoration: BoxDecoration(
                                        color: AppTheme.primary.withValues(alpha: 0.06),
                                        borderRadius: BorderRadius.circular(10),
                                        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.15)),
                                      ),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              const Icon(Icons.storefront_rounded, size: 13, color: AppTheme.primary),
                                              const SizedBox(width: 5),
                                              Text(
                                                'Balasan Pemilik',
                                                style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: AppTheme.primary),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 4),
                                          Text(review.ownerReply!, style: Theme.of(context).textTheme.bodySmall),
                                        ],
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            );
                          }),
                        const SizedBox(height: 30),

                        _sectionTitle('Kos Serupa di ${kos.location}'),
                        _SimilarKosSection(
                          kosId: kos.id,
                          futureBuilder: () {
                            if (_similarForKosId != kos.id) {
                              _similarForKosId = kos.id;
                              _similarKosesFuture = Provider.of<KosProvider>(
                                context,
                                listen: false,
                              ).fetchSimilarKoses(kos);
                            }
                            return _similarKosesFuture!;
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          SafeArea(
            child: Container(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                boxShadow: AppTheme.softShadow(opacity: 0.10),
              ),
              child: Row(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Mulai dari',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      Text(
                        _formatPrice(kos.price),
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w800,
                          color: AppTheme.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: PremiumButton(
                      label: kos.availableRooms > 0
                          ? 'Ajukan Booking'
                          : 'Kos Penuh',
                      icon: kos.availableRooms > 0
                          ? Icons.calendar_month_rounded
                          : null,
                      onPressed: kos.availableRooms > 0
                          ? () => context.push('/booking/new', extra: kos)
                          : null,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ).animate().fadeIn(duration: 240.ms);
  }
}

/// Grafik batang jumlah ulasan per bintang (5 -> 1) -- kasih gambaran cepat
/// distribusi rating, bukan cuma angka rata-rata.
class _RatingBreakdownBars extends StatelessWidget {
  final Kos kos;

  const _RatingBreakdownBars({required this.kos});

  @override
  Widget build(BuildContext context) {
    final maxCount = kos.ratingBreakdown.values
        .fold(0, (a, b) => a > b ? a : b)
        .clamp(1, 1 << 30);

    return Column(
      children: [5, 4, 3, 2, 1].map((star) {
        final count = kos.ratingBreakdown[star] ?? 0;
        final ratio = count / maxCount;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(
            children: [
              SizedBox(
                width: 28,
                child: Text(
                  '$star ★',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: ratio,
                    minHeight: 8,
                    backgroundColor: Theme.of(
                      context,
                    ).inputDecorationTheme.fillColor,
                    valueColor: const AlwaysStoppedAnimation(AppTheme.warning),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 22,
                child: Text(
                  '$count',
                  style: Theme.of(context).textTheme.bodySmall,
                  textAlign: TextAlign.end,
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}

/// Kos lain di lokasi yang sama -- bantu penyewa bandingkan pilihan tanpa
/// harus keluar-masuk halaman pencarian.
class _SimilarKosSection extends StatelessWidget {
  final int kosId;
  final Future<List<Kos>> Function() futureBuilder;

  const _SimilarKosSection({required this.kosId, required this.futureBuilder});

  String _formatPrice(int price) => price >= 1000000
      ? 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln'
      : 'Rp $price/bln';

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Kos>>(
      future: futureBuilder(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const SizedBox(
            height: 160,
            child: Row(
              children: [
                SkeletonBox(
                  width: 150,
                  height: 160,
                  borderRadius: BorderRadius.all(Radius.circular(16)),
                ),
              ],
            ),
          );
        }
        final similar = snapshot.data ?? [];
        if (similar.isEmpty) {
          return Text(
            'Belum ada kos lain di lokasi ini.',
            style: Theme.of(context).textTheme.bodySmall,
          );
        }
        return SizedBox(
          height: 172,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: similar.length,
            separatorBuilder: (_, __) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final kos = similar[index];
              return GestureDetector(
                onTap: () => context.pushReplacement('/kos/${kos.id}'),
                child: Container(
                  width: 150,
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color:
                          Theme.of(context).dividerTheme.color ??
                          Colors.transparent,
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: const BorderRadius.vertical(
                          top: Radius.circular(16),
                        ),
                        child: CachedNetworkImage(
                          imageUrl: kos.coverImage,
                          height: 90,
                          width: double.infinity,
                          fit: BoxFit.cover,
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              kos.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 12,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              _formatPrice(kos.price),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 11.5,
                                color: AppTheme.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
