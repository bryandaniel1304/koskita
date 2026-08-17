import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/auth_provider.dart';
import '../providers/kos_provider.dart';
import '../providers/notification_provider.dart';
import '../providers/message_provider.dart';
import '../models/kos.dart';
import '../models/saved_filter.dart';
import '../widgets/error_state.dart';
import '../widgets/skeleton_box.dart';
import '../widgets/onboarding_tips_sheet.dart';
import '../widgets/changelog_sheet.dart';
import '../widgets/premium_button.dart';
import '../widgets/user_avatar.dart';
import '../config/app_theme.dart';
import '../utils/haptics.dart';
import '../utils/undo_snackbar.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _searchController = TextEditingController();
  String? _selectedGender;
  String? _selectedLocation;
  RangeValues? _budgetRange;
  final Set<int> _selectedFacilityIds = {};

  // 'terbaru' (bukan null) SENGAJA -- PopupMenuButton<T> memakai `null`
  // sebagai penanda internal "menu ditutup tanpa pilih apa-apa" (lihat
  // showMenu), jadi PopupMenuItem dengan value: null TIDAK PERNAH memanggil
  // onSelected saat diketuk. Pakai sentinel string biasa supaya semua
  // opsi (termasuk "Terbaru") benar-benar bisa dipilih.
  String _selectedSort = 'terbaru';

  String _sortLabel(String sort) {
    switch (sort) {
      case 'price_asc':
        return 'Termurah';
      case 'price_desc':
        return 'Tertinggi';
      case 'distance':
        return 'Terdekat';
      case 'rating':
        return 'Rating';
      default:
        return 'Terbaru';
    }
  }

  bool get _hasAdvancedFilters => _budgetRange != null || _selectedFacilityIds.isNotEmpty;
  bool get _hasAnyFilter =>
      _hasAdvancedFilters || _searchController.text.isNotEmpty || _selectedGender != null || _selectedLocation != null;

  // Mode "Bandingkan" langsung dari hasil pencarian -- pola & batas (maks 3)
  // PERSIS sama dengan yang sudah ada di FavoritesScreen, cuma sumber
  // datanya beda (kosProvider.koses, bukan kosProvider.favorites).
  bool _compareMode = false;
  final Set<int> _selectedCompareIds = {};

  void _toggleCompareMode() {
    setState(() {
      _compareMode = !_compareMode;
      if (!_compareMode) _selectedCompareIds.clear();
    });
  }

  void _openComparison(List<Kos> koses) {
    final selected = koses.where((k) => _selectedCompareIds.contains(k.id)).toList();
    context.push('/compare', extra: selected);
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadData();
      final kosProvider = Provider.of<KosProvider>(context, listen: false);
      kosProvider.fetchFacilitiesMeta();
      kosProvider.loadRecentlyViewed();
      kosProvider.loadSavedFilters();
      Provider.of<NotificationProvider>(context, listen: false).fetchNotifications();
      Provider.of<MessageProvider>(context, listen: false).fetchUnreadCount();
      OnboardingTipsSheet.showOnceIfNeeded(
        context: context,
        storageKey: 'onboarding_tips_tenant_v1',
        headline: 'Selamat Datang di KosKita',
        tips: const [
          OnboardingTip(
            icon: Icons.auto_awesome_rounded,
            title: 'Rekomendasi Otomatis',
            description: 'Beri rating kos yang kamu suka -- rekomendasi di beranda makin akurat mengikuti preferensimu.',
          ),
          OnboardingTip(
            icon: Icons.tune_rounded,
            title: 'Filter & Urutkan',
            description: 'Saring berdasarkan budget/fasilitas, atau urutkan dari yang termurah/terdekat lewat menu di atas daftar kos.',
          ),
          OnboardingTip(
            icon: Icons.chat_bubble_rounded,
            title: 'Chat Langsung ke Pemilik',
            description: 'Tanya-tanya soal kos langsung dari halaman detail, tanpa perlu keluar aplikasi.',
          ),
        ],
      );
      // Cuma tampil sekali begitu terdeteksi baru saja update ke versi
      // baru -- tidak akan tabrakan dengan onboarding di atas (yang cuma
      // untuk akun benar-benar baru), lihat AppVersionService.
      ChangelogSheet.maybeShow(context);
    });
  }

  void _applySavedFilter(SavedFilter filter) {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    setState(() {
      _searchController.text = filter.search ?? '';
      _selectedGender = filter.genderType;
      _selectedLocation = filter.location;
      _budgetRange = (filter.budgetMin != null && filter.budgetMax != null)
          ? RangeValues(filter.budgetMin!.toDouble(), filter.budgetMax!.toDouble())
          : null;
      _selectedFacilityIds
        ..clear()
        ..addAll(filter.facilityIds);
    });
    _applyFilters(kosProvider);
  }

  Future<void> _promptSaveFilter() async {
    final controller = TextEditingController();
    final name = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Simpan Filter Ini'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLength: 30,
          decoration: const InputDecoration(hintText: 'Mis. "Deket Kampus, Budget Pas"'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
    if (name == null || name.isEmpty || !mounted) return;

    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    final saved = await kosProvider.saveFilter(SavedFilter(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      name: name,
      search: _searchController.text.isNotEmpty ? _searchController.text : null,
      genderType: _selectedGender,
      location: _selectedLocation,
      budgetMin: _budgetRange?.start.round(),
      budgetMax: _budgetRange?.end.round(),
      facilityIds: _selectedFacilityIds.toList(),
    ));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(saved ? 'Filter "$name" disimpan.' : 'Gagal menyimpan filter.')),
    );
  }

  // Chip "x" sudah cukup sengaja/bertarget untuk dianggap gesture yang
  // disadari (bukan swipe tak sengaja) -- dialog konfirmasi 2 langkah yang
  // lama diganti pola "hapus dulu, tawarkan Batalkan" yang lebih cepat
  // tapi tetap aman kalau ternyata salah pencet.
  Future<void> _deleteSavedFilter(SavedFilter filter) async {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    Haptics.light();
    await kosProvider.deleteSavedFilter(filter.id);
    if (!mounted) return;
    showUndoSnackBar(
      context,
      message: 'Filter "${filter.name}" dihapus',
      onUndo: () => kosProvider.saveFilter(filter),
    );
  }

  void _loadData() {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    _applyFilters(kosProvider);
    kosProvider.fetchRecommendations();
  }

  void _applyFilters(KosProvider kosProvider) {
    kosProvider.fetchKoses(
      search: _searchController.text,
      genderType: _selectedGender,
      location: _selectedLocation,
      budgetMin: _budgetRange?.start.round(),
      budgetMax: _budgetRange?.end.round(),
      facilityIds: _selectedFacilityIds.isNotEmpty ? _selectedFacilityIds.toList() : null,
      sort: _selectedSort == 'terbaru' ? null : _selectedSort,
    );
  }

  Future<void> _openFilterSheet() async {
    final kosProvider = Provider.of<KosProvider>(context, listen: false);
    RangeValues tempRange = _budgetRange ?? const RangeValues(500000, 6000000);
    final tempFacilities = Set<int>.from(_selectedFacilityIds);

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            return Container(
              padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(sheetContext).viewInsets.bottom + 20),
              decoration: BoxDecoration(
                color: Theme.of(context).scaffoldBackgroundColor,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(4)),
                    ),
                  ),
                  Text('Filter Lanjutan', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Rentang Budget', style: Theme.of(context).textTheme.titleMedium),
                      Text(
                        'Rp ${(tempRange.start / 1000000).toStringAsFixed(1)}jt - Rp ${(tempRange.end / 1000000).toStringAsFixed(1)}jt',
                        style: const TextStyle(fontWeight: FontWeight.w800, color: AppTheme.primary, fontSize: 13),
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
                      values: tempRange,
                      min: 500000,
                      max: 6000000,
                      divisions: 11,
                      onChanged: (values) => setSheetState(() => tempRange = values),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text('Fasilitas', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: kosProvider.facilities.map((f) {
                      final selected = tempFacilities.contains(f.id);
                      return FilterChip(
                        label: Text(f.name),
                        selected: selected,
                        onSelected: (v) => setSheetState(() => v ? tempFacilities.add(f.id) : tempFacilities.remove(f.id)),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 22),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            setSheetState(() {
                              tempRange = const RangeValues(500000, 6000000);
                              tempFacilities.clear();
                            });
                          },
                          child: const Text('Reset'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton(
                          onPressed: () {
                            setState(() {
                              _budgetRange = tempRange;
                              _selectedFacilityIds
                                ..clear()
                                ..addAll(tempFacilities);
                            });
                            Navigator.pop(sheetContext);
                            _applyFilters(kosProvider);
                          },
                          child: const Text('Terapkan Filter'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  String _formatPrice(int price) {
    if (price >= 1000000) {
      return 'Rp ${(price / 1000000).toStringAsFixed(1)} jt/bln';
    }
    return 'Rp $price/bln';
  }

  Widget _ratingBadge(BuildContext context, Kos kos, {double size = 11}) {
    if (kos.averageReviewRating == null) return const SizedBox.shrink();
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.star_rounded, size: size + 3, color: AppTheme.warning),
        const SizedBox(width: 2),
        // Warna adaptif tema -- AppTheme.ink hardcode hampir-hitam bikin
        // angka rating ini tak kebaca di kartu kos saat mode gelap.
        Text(kos.averageReviewRating!.toStringAsFixed(1), style: TextStyle(fontSize: size, fontWeight: FontWeight.w700, color: Theme.of(context).textTheme.bodyLarge?.color)),
      ],
    );
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
    final firstName = (user?.name ?? 'Pengguna').split(' ').first;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: (kosProvider.isLoading && kosProvider.koses.isEmpty)
          ? _buildSkeleton(context)
          : showEmptyState
              ? SafeArea(child: ErrorStateView(message: kosProvider.errorMessage!, onRetry: _loadData))
              : RefreshIndicator(
                  onRefresh: () async {
                    _loadData();
                  },
                  child: CustomScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    slivers: [
                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              SafeArea(
                                bottom: false,
                                child: Row(
                                  children: [
                                    UserAvatar(avatarUrl: user?.avatarUrl, name: firstName.isNotEmpty ? firstName : 'K', size: 46),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text('Halo, $firstName 👋', style: Theme.of(context).textTheme.headlineSmall),
                                          const SizedBox(height: 2),
                                          Row(
                                            children: [
                                              const Icon(Icons.location_on_rounded, size: 14, color: AppTheme.primaryLight),
                                              const SizedBox(width: 3),
                                              Text(
                                                user?.profile?.preferredLocation ?? 'Lokasi belum diset',
                                                style: Theme.of(context).textTheme.bodySmall,
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.map_outlined),
                                      tooltip: 'Peta Kos',
                                      onPressed: () => context.push('/map'),
                                    ),
                                    Consumer<MessageProvider>(
                                      builder: (context, msg, _) => Stack(
                                        clipBehavior: Clip.none,
                                        children: [
                                          IconButton(
                                            icon: const Icon(Icons.chat_bubble_outline_rounded),
                                            tooltip: 'Pesan',
                                            onPressed: () => context.push('/messages'),
                                          ),
                                          if (msg.unreadCount > 0)
                                            Positioned(
                                              top: 8,
                                              right: 8,
                                              child: Container(
                                                width: 9,
                                                height: 9,
                                                decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                    Consumer<NotificationProvider>(
                                      builder: (context, notif, _) => Stack(
                                        clipBehavior: Clip.none,
                                        children: [
                                          IconButton(
                                            icon: const Icon(Icons.notifications_outlined),
                                            tooltip: 'Notifikasi',
                                            onPressed: () => context.push('/notifications'),
                                          ),
                                          if (notif.notifications.isNotEmpty)
                                            Positioned(
                                              top: 8,
                                              right: 8,
                                              child: Container(
                                                width: 9,
                                                height: 9,
                                                decoration: const BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle),
                                              ),
                                            ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 22),

                              // Banner rekomendasi otomatis (tidak ada tombol, langsung tampil)
                              Container(
                                padding: const EdgeInsets.all(18),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(22),
                                  gradient: kosProvider.isColdStart
                                      ? AppTheme.primaryGradient
                                      : const LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                                  boxShadow: AppTheme.glowShadow(
                                    kosProvider.isColdStart ? AppTheme.primary : const Color(0xFF10B981),
                                    opacity: 0.28,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(11),
                                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(14)),
                                      child: Icon(
                                        kosProvider.isColdStart ? Icons.auto_awesome_rounded : Icons.flash_on_rounded,
                                        color: Colors.white,
                                        size: 22,
                                      ),
                                    ),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            kosProvider.isColdStart ? 'Rekomendasi Untukmu' : 'Rekomendasi Makin Akurat',
                                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14.5),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            kosProvider.isColdStart
                                                ? 'Berdasarkan preferensimu. Beri rating kos yang kamu suka biar makin akurat.'
                                                : 'Disesuaikan dari ${kosProvider.ratingCount} rating yang sudah kamu berikan.',
                                            style: const TextStyle(color: Colors.white70, fontSize: 11.5, height: 1.4),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ).animate().fadeIn(duration: 320.ms).slideY(begin: 0.08, end: 0),
                              const SizedBox(height: 26),

                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text('Rekomendasi Terbaik Untukmu', style: Theme.of(context).textTheme.titleLarge),
                                  if (kosProvider.recommendations.isEmpty) Text('Tidak ada data', style: Theme.of(context).textTheme.bodySmall),
                                ],
                              ),
                              const SizedBox(height: 12),
                            ],
                          ),
                        ),
                      ),

                      if (kosProvider.recommendations.isEmpty)
                        SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            child: Container(
                              height: 130,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: Theme.of(context).cardColor,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
                              ),
                              child: Text('Silakan lengkapi profil preferensi kamu.', style: Theme.of(context).textTheme.bodyMedium),
                            ),
                          ),
                        )
                      else
                        SliverToBoxAdapter(
                          child: SizedBox(
                            height: 266,
                            child: ListView.builder(
                              padding: const EdgeInsets.only(left: 20, right: 8),
                              scrollDirection: Axis.horizontal,
                              itemCount: kosProvider.recommendations.length,
                              itemBuilder: (context, index) {
                                final rec = kosProvider.recommendations[index];
                                final kos = rec['kos'] as Kos;
                                final match = rec['match_percentage'] as int;

                                return GestureDetector(
                                  onTap: () => context.push('/kos/${kos.id}'),
                                  child: Container(
                                    width: 202,
                                    margin: const EdgeInsets.only(right: 14),
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).cardColor,
                                      borderRadius: BorderRadius.circular(20),
                                      boxShadow: AppTheme.softShadow(),
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Stack(
                                          children: [
                                            ClipRRect(
                                              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                                              child: CachedNetworkImage(
                                                imageUrl: kos.coverImage,
                                                height: 122,
                                                width: double.infinity,
                                                fit: BoxFit.cover,
                                                placeholder: (context, url) => const SkeletonBox(height: 122, borderRadius: BorderRadius.zero),
                                                errorWidget: (context, url, error) => Container(height: 122, color: Colors.grey[300], child: const Icon(Icons.image, color: Colors.grey)),
                                              ),
                                            ),
                                            Positioned(
                                              top: 8,
                                              right: 8,
                                              child: Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                                                decoration: BoxDecoration(
                                                  gradient: kosProvider.isColdStart ? AppTheme.primaryGradient : const LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)]),
                                                  borderRadius: BorderRadius.circular(20),
                                                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2))],
                                                ),
                                                child: Text('$match% Cocok', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
                                              ),
                                            ),
                                            Positioned(
                                              bottom: 8,
                                              left: 8,
                                              child: Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.55), borderRadius: BorderRadius.circular(8)),
                                                child: Text(kos.genderType.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
                                              ),
                                            ),
                                          ],
                                        ),
                                        Expanded(
                                          child: Padding(
                                            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                              children: [
                                                Text(kos.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                                                Row(
                                                  children: [
                                                    const Icon(Icons.directions_walk_rounded, size: 12, color: AppTheme.muted),
                                                    const SizedBox(width: 2),
                                                    Text('${kos.distanceToCampus} km', style: Theme.of(context).textTheme.bodySmall),
                                                    if (kos.averageReviewRating != null) ...[
                                                      const SizedBox(width: 8),
                                                      _ratingBadge(context, kos),
                                                    ],
                                                  ],
                                                ),
                                                Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: AppTheme.primary)),
                                              ],
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ).animate(delay: (index * 60).ms).fadeIn(duration: 300.ms).slideX(begin: 0.12, end: 0);
                              },
                            ),
                          ),
                        ),

                      if (kosProvider.recentlyViewed.isNotEmpty) ...[
                        SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(20, 28, 20, 12),
                            child: Text('Terakhir Dilihat', style: Theme.of(context).textTheme.titleLarge),
                          ),
                        ),
                        SliverToBoxAdapter(
                          child: SizedBox(
                            height: 96,
                            child: ListView.builder(
                              padding: const EdgeInsets.only(left: 20, right: 8),
                              scrollDirection: Axis.horizontal,
                              itemCount: kosProvider.recentlyViewed.length,
                              itemBuilder: (context, index) {
                                final kos = kosProvider.recentlyViewed[index];
                                return GestureDetector(
                                  onTap: () => context.push('/kos/${kos.id}'),
                                  child: Container(
                                    width: 210,
                                    margin: const EdgeInsets.only(right: 12),
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).cardColor,
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
                                    ),
                                    child: Row(
                                      children: [
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(10),
                                          child: CachedNetworkImage(
                                            imageUrl: kos.coverImage,
                                            width: 60,
                                            height: 60,
                                            fit: BoxFit.cover,
                                            placeholder: (context, url) => const SkeletonBox(width: 60, height: 60),
                                            errorWidget: (context, url, error) => Container(width: 60, height: 60, color: Colors.grey[300], child: const Icon(Icons.image, color: Colors.grey, size: 20)),
                                          ),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            mainAxisAlignment: MainAxisAlignment.center,
                                            children: [
                                              Text(kos.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                                              const SizedBox(height: 4),
                                              Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: AppTheme.primary)),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ).animate(delay: (index * 50).ms).fadeIn(duration: 260.ms);
                              },
                            ),
                          ),
                        ),
                      ],

                      SliverToBoxAdapter(
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(20, 28, 20, 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text('Semua Kos Tersedia', style: Theme.of(context).textTheme.titleLarge),
                                  PopupMenuButton<String>(
                                    initialValue: _selectedSort,
                                    onSelected: (value) {
                                      setState(() => _selectedSort = value);
                                      _applyFilters(kosProvider);
                                    },
                                    itemBuilder: (context) => const [
                                      PopupMenuItem(value: 'terbaru', child: Text('Terbaru')),
                                      PopupMenuItem(value: 'price_asc', child: Text('Harga Termurah')),
                                      PopupMenuItem(value: 'price_desc', child: Text('Harga Tertinggi')),
                                      PopupMenuItem(value: 'distance', child: Text('Terdekat dari Kampus')),
                                      PopupMenuItem(value: 'rating', child: Text('Rating Tertinggi')),
                                    ],
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Text(_sortLabel(_selectedSort), style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: AppTheme.primary)),
                                        const Icon(Icons.arrow_drop_down_rounded, color: AppTheme.primary, size: 18),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              if (kosProvider.isShowingCachedKoses) ...[
                                const SizedBox(height: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                  decoration: BoxDecoration(color: AppTheme.warning.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.cloud_off_rounded, size: 15, color: Color(0xFFB45309)),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          'Menampilkan data tersimpan -- tidak bisa terhubung ke server saat ini.',
                                          style: const TextStyle(fontSize: 11.5, color: Color(0xFF92400E), fontWeight: FontWeight.w600),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              const SizedBox(height: 12),
                              TextField(
                                controller: _searchController,
                                decoration: InputDecoration(
                                  hintText: 'Cari nama kos atau lokasi...',
                                  prefixIcon: const Icon(Icons.search_rounded),
                                  suffixIcon: IconButton(
                                    icon: const Icon(Icons.clear_rounded),
                                    tooltip: 'Hapus pencarian',
                                    onPressed: () {
                                      _searchController.clear();
                                      _applyFilters(kosProvider);
                                    },
                                  ),
                                ),
                                onSubmitted: (_) => _applyFilters(kosProvider),
                              ),
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  Expanded(
                                    child: DropdownButtonFormField<String>(
                                      initialValue: _selectedGender,
                                      decoration: const InputDecoration(hintText: 'Semua Tipe'),
                                      items: const [
                                        DropdownMenuItem(value: null, child: Text('Semua Tipe')),
                                        DropdownMenuItem(value: 'putra', child: Text('Putra')),
                                        DropdownMenuItem(value: 'putri', child: Text('Putri')),
                                        DropdownMenuItem(value: 'campur', child: Text('Campur')),
                                      ],
                                      onChanged: (val) {
                                        setState(() => _selectedGender = val);
                                        _applyFilters(kosProvider);
                                      },
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: DropdownButtonFormField<String>(
                                      initialValue: _selectedLocation,
                                      decoration: const InputDecoration(hintText: 'Semua Area'),
                                      items: const [
                                        DropdownMenuItem(value: null, child: Text('Semua Area')),
                                        DropdownMenuItem(value: 'Karawaci', child: Text('Karawaci')),
                                        DropdownMenuItem(value: 'BSD', child: Text('BSD')),
                                        DropdownMenuItem(value: 'Serpong', child: Text('Serpong')),
                                      ],
                                      onChanged: (val) {
                                        setState(() => _selectedLocation = val);
                                        _applyFilters(kosProvider);
                                      },
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Stack(
                                    clipBehavior: Clip.none,
                                    children: [
                                      Container(
                                        decoration: BoxDecoration(
                                          color: _hasAdvancedFilters ? AppTheme.primary : Theme.of(context).inputDecorationTheme.fillColor,
                                          borderRadius: BorderRadius.circular(14),
                                        ),
                                        child: IconButton(
                                          icon: Icon(Icons.tune_rounded, color: _hasAdvancedFilters ? Colors.white : AppTheme.muted),
                                          tooltip: 'Filter lanjutan',
                                          onPressed: _openFilterSheet,
                                        ),
                                      ),
                                      if (_hasAdvancedFilters)
                                        Positioned(
                                          top: -2,
                                          right: -2,
                                          child: Container(
                                            width: 10,
                                            height: 10,
                                            decoration: BoxDecoration(color: AppTheme.danger, shape: BoxShape.circle, border: Border.all(color: Theme.of(context).scaffoldBackgroundColor, width: 1.5)),
                                          ),
                                        ),
                                    ],
                                  ),
                                  if (_hasAnyFilter) ...[
                                    const SizedBox(width: 10),
                                    Container(
                                      decoration: BoxDecoration(
                                        color: Theme.of(context).inputDecorationTheme.fillColor,
                                        borderRadius: BorderRadius.circular(14),
                                      ),
                                      child: IconButton(
                                        icon: const Icon(Icons.bookmark_add_outlined, color: AppTheme.muted),
                                        tooltip: 'Simpan filter ini',
                                        onPressed: _promptSaveFilter,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              if (kosProvider.savedFilters.isNotEmpty) ...[
                                const SizedBox(height: 12),
                                SizedBox(
                                  height: 34,
                                  child: ListView.separated(
                                    scrollDirection: Axis.horizontal,
                                    itemCount: kosProvider.savedFilters.length,
                                    separatorBuilder: (context, index) => const SizedBox(width: 8),
                                    itemBuilder: (context, i) {
                                      final filter = kosProvider.savedFilters[i];
                                      return InputChip(
                                        avatar: const Icon(Icons.bookmark_rounded, size: 15, color: AppTheme.primary),
                                        label: Text(filter.name, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600)),
                                        onPressed: () => _applySavedFilter(filter),
                                        onDeleted: () => _deleteSavedFilter(filter),
                                        deleteIconColor: AppTheme.muted,
                                      );
                                    },
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),

                      if (kosProvider.koses.isEmpty)
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.symmetric(vertical: 12),
                            child: ErrorStateView.empty(
                              message: 'Tidak ada kos yang cocok dengan pencarianmu.\nCoba ganti kata kunci atau longgarkan filternya.',
                              icon: Icons.search_off_rounded,
                            ),
                          ),
                        )
                      else ...[
                        if (kosProvider.koses.length >= 2)
                          SliverToBoxAdapter(
                            child: Padding(
                              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                              child: Align(
                                alignment: Alignment.centerRight,
                                child: TextButton.icon(
                                  onPressed: _toggleCompareMode,
                                  icon: Icon(_compareMode ? Icons.close_rounded : Icons.compare_arrows_rounded, size: 18),
                                  label: Text(_compareMode ? 'Batal Bandingkan' : 'Bandingkan Kos'),
                                ),
                              ),
                            ),
                          ),
                        SliverPadding(
                          padding: const EdgeInsets.fromLTRB(20, 0, 20, 100),
                          sliver: SliverList.builder(
                            // +1 buat footer loading -- dibangun (dan sekaligus
                            // memicu fetchMoreKoses()) begitu pengguna scroll
                            // mendekati akhir daftar. "/kos" sekarang dipaginasi
                            // 20/halaman di backend, bukan seluruh hasil sekaligus.
                            itemCount: kosProvider.koses.length + (kosProvider.hasMorePages ? 1 : 0),
                            itemBuilder: (context, index) {
                              if (index >= kosProvider.koses.length) {
                                WidgetsBinding.instance.addPostFrameCallback((_) => kosProvider.fetchMoreKoses());
                                return const Padding(
                                  padding: EdgeInsets.symmetric(vertical: 20),
                                  child: Center(child: CircularProgressIndicator()),
                                );
                              }
                              final kos = kosProvider.koses[index];
                              final compareSelected = _selectedCompareIds.contains(kos.id);
                              return GestureDetector(
                                onTap: _compareMode
                                    ? () {
                                        setState(() {
                                          // Tidak ada batas jumlah lagi -- CompareScreen
                                          // sudah bisa discroll ke samping berapa pun
                                          // banyaknya kos yang dipilih.
                                          if (compareSelected) {
                                            _selectedCompareIds.remove(kos.id);
                                          } else {
                                            _selectedCompareIds.add(kos.id);
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
                                    border: compareSelected ? Border.all(color: AppTheme.primary, width: 1.6) : null,
                                    boxShadow: AppTheme.softShadow(opacity: 0.05),
                                  ),
                                  child: Row(
                                    children: [
                                      if (_compareMode) ...[
                                        Icon(
                                          compareSelected ? Icons.check_circle_rounded : Icons.circle_outlined,
                                          color: compareSelected ? AppTheme.primary : AppTheme.muted,
                                        ),
                                        const SizedBox(width: 10),
                                      ],
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(14),
                                        child: CachedNetworkImage(
                                          imageUrl: kos.coverImage,
                                          width: 92,
                                          height: 92,
                                          fit: BoxFit.cover,
                                          placeholder: (context, url) => const SkeletonBox(width: 92, height: 92),
                                          errorWidget: (context, url, error) => Container(width: 92, height: 92, color: Colors.grey[300], child: const Icon(Icons.image, color: Colors.grey)),
                                        ),
                                      ),
                                      const SizedBox(width: 14),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                                              decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(6)),
                                              child: Text(kos.genderType.toUpperCase(), style: const TextStyle(fontSize: 8.5, fontWeight: FontWeight.w800, color: AppTheme.primary)),
                                            ),
                                            const SizedBox(height: 5),
                                            Text(kos.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                                            const SizedBox(height: 4),
                                            Row(
                                              children: [
                                                const Icon(Icons.location_on_rounded, size: 12, color: AppTheme.muted),
                                                const SizedBox(width: 2),
                                                Text(kos.location, style: Theme.of(context).textTheme.bodySmall),
                                                const SizedBox(width: 8),
                                                const Icon(Icons.directions_walk_rounded, size: 12, color: AppTheme.muted),
                                                const SizedBox(width: 2),
                                                Text('${kos.distanceToCampus} km', style: Theme.of(context).textTheme.bodySmall),
                                              ],
                                            ),
                                            const SizedBox(height: 8),
                                            Row(
                                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                              children: [
                                                Text(_formatPrice(kos.price), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: AppTheme.primary)),
                                                _ratingBadge(context, kos),
                                              ],
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ).animate(delay: (index.clamp(0, 6) * 45).ms).fadeIn(duration: 260.ms);
                            },
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
      bottomNavigationBar: _compareMode && _selectedCompareIds.length >= 2
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: PremiumButton(
                  label: 'Bandingkan ${_selectedCompareIds.length} Kos',
                  icon: Icons.compare_arrows_rounded,
                  onPressed: () => _openComparison(kosProvider.koses),
                ),
              ),
            )
          : null,
    );
  }

  Widget _buildSkeleton(BuildContext context) {
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Row(
            children: [
              const SkeletonBox(width: 46, height: 46, borderRadius: BorderRadius.all(Radius.circular(23))),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    SkeletonBox(height: 16, width: 140),
                    SizedBox(height: 8),
                    SkeletonBox(height: 11, width: 90),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          const SkeletonBox(height: 90, borderRadius: BorderRadius.all(Radius.circular(22))),
          const SizedBox(height: 26),
          const SkeletonBox(height: 18, width: 180),
          const SizedBox(height: 12),
          const SkeletonBox(height: 240, borderRadius: BorderRadius.all(Radius.circular(20))),
          const SizedBox(height: 26),
          const SkeletonBox(height: 18, width: 150),
          const SizedBox(height: 12),
          const SkeletonKosCard(),
          const SizedBox(height: 14),
          const SkeletonKosCard(),
          const SizedBox(height: 14),
          const SkeletonKosCard(),
        ],
      ),
    );
  }
}
