import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../providers/auth_provider.dart';
import '../services/google_auth_service.dart';
import '../utils/legal_links.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../widgets/premium_button.dart';

/// Layar Masuk/Daftar -- sengaja dibangun di atas SATU SingleChildScrollView
/// polos (tanpa header terpisah/Transform overlap/Expanded bertingkat).
/// Struktur "hero besar + kartu mengambang" yang dipakai sebelumnya rapuh
/// terhadap perilaku scroll-otomatis Flutter saat keyboard muncul (field
/// yang di-fokus bisa membuat header kepotong setengah). Layout datar biasa
/// begini justru lebih "profesional" (mirip Stripe/Linear/Notion -- bukan
/// hero warna-warni) DAN kebal dari kelas bug itu sama sekali, karena tidak
/// ada elemen yang posisinya dihitung manual relatif ke elemen lain.
class LoginRegisterScreen extends StatefulWidget {
  const LoginRegisterScreen({super.key});

  @override
  State<LoginRegisterScreen> createState() => _LoginRegisterScreenState();
}

class _LoginRegisterScreenState extends State<LoginRegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isLogin = true;
  bool _obscurePassword = true;

  // Sengaja tidak diberi nilai awal -- pengguna WAJIB memilih sendiri
  // sebelum bisa mendaftar, tidak ada peran yang "sudah terpilih" duluan.
  String? _role;
  bool _agreedToTerms = false;

  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();

  static final _phoneRegex = RegExp(r'^(\+62|62|0)8[1-9][0-9]{6,10}$');

  // false by default -- tombol "Masuk dengan Google" baru muncul setelah
  // dipastikan backend memang sudah dikonfigurasi (GET /auth/google/config),
  // supaya tidak tampil tapi selalu gagal kalau ditekan.
  bool _googleConfigured = false;
  bool _isGoogleLoading = false;

  @override
  void initState() {
    super.initState();
    GoogleAuthService.isConfigured().then((configured) {
      if (mounted) setState(() => _googleConfigured = configured);
    });
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (!_isLogin && _role == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pilih dulu kamu mendaftar sebagai apa.'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    if (!_isLogin && !_agreedToTerms) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Kamu perlu menyetujui Syarat & Ketentuan dan Kebijakan Privasi dulu.',
          ),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    bool success = false;

    if (_isLogin) {
      final result = await authProvider.login(
        _emailController.text.trim(),
        _passwordController.text.trim(),
      );
      if (!mounted) return;
      if (result == LoginResult.requiresTwoFactor) {
        // Password sudah benar, tapi belum benar-benar login sampai kode
        // dari email dimasukkan -- lempar ke layar kode, BUKAN langsung
        // dianggap gagal/berhasil.
        context.push('/verifikasi-2fa');
        return;
      }
      success = result == LoginResult.success;
    } else {
      success = await authProvider.register(
        _nameController.text.trim(),
        _emailController.text.trim(),
        _phoneController.text.trim(),
        _passwordController.text.trim(),
        _role!,
      );
    }

    if (!mounted) return;

    if (success) {
      final role = authProvider.user?.role ?? 'user';
      final isOwner = role == 'owner';
      if (_isLogin) {
        context.go(isOwner ? '/owner/koses' : '/home');
      } else if (isOwner) {
        // Pemilik kos tidak punya profil preferensi (khusus penyewa) --
        // langsung ke dashboard kelola kos.
        context.go('/owner/koses');
      } else {
        // Baru mendaftar -> langsung ke Beranda (bukan Onboarding lagi).
        // Profil preferensi tetap ada nilai default (dibuat backend saat
        // registrasi) supaya rekomendasi cold-start tetap jalan; pengguna
        // bisa sesuaikan kapan saja lewat Profil > Edit Profil Preferensi.
        context.go('/home');
      }
    } else {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            authProvider.errorMessage ??
                (_isLogin
                    ? 'Login gagal. Periksa email/password.'
                    : 'Registrasi gagal. Coba lagi.'),
          ),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  /// Berlaku sama untuk mode Masuk MAUPUN Daftar -- akun Google SELALU
  /// dibuat sebagai penyewa (role "user") di backend, persis seperti alur
  /// web (lihat GoogleAccountService), jadi pilihan peran/persetujuan
  /// Syarat & Ketentuan di atas tidak relevan untuk tombol ini.
  Future<void> _submitGoogle() async {
    setState(() => _isGoogleLoading = true);
    try {
      final signInResult = await GoogleAuthService.signIn();
      if (signInResult == null) return; // batal pilih akun / gagal di perangkat, diam saja
      if (!mounted) return;

      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final result = await authProvider.loginWithGoogle(signInResult.idToken, signInResult.email);
      if (!mounted) return;

      if (result == LoginResult.requiresTwoFactor) {
        context.push('/verifikasi-2fa');
        return;
      }

      if (result == LoginResult.success) {
        final isOwner = authProvider.user?.role == 'owner';
        context.go(isOwner ? '/owner/koses' : '/home');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(authProvider.errorMessage ?? 'Gagal masuk dengan Google. Coba lagi.'),
            backgroundColor: AppTheme.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isGoogleLoading = false);
    }
  }

  /// Dialog untuk mengisi alamat server backend secara manual, tersimpan
  /// permanen di HP (SharedPreferences) -- tanpa ini, app yang di-install
  /// & dibuka langsung dari ikon (bukan `flutter run --dart-define`) akan
  /// selalu memakai alamat default yang cuma valid untuk Android Emulator,
  /// bikin login gagal terus dengan pesan "Server tidak merespons" di HP
  /// fisik.
  Future<void> _showServerSettingsDialog() async {
    final controller = TextEditingController(
      text: AppConfig.currentOverride.isNotEmpty
          ? AppConfig.currentOverride
          : AppConfig.apiBaseUrl,
    );

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Pengaturan Server'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Isi alamat IP komputer yang menjalankan backend (contoh: '
              'http://192.168.1.5:8000/api). Cuma dibutuhkan kalau app '
              'dibuka langsung di HP dan login gagal karena "server tidak '
              'merespons".',
              style: TextStyle(fontSize: 12.5),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              keyboardType: TextInputType.url,
              decoration: const InputDecoration(
                labelText: 'Alamat API',
                hintText: 'http://192.168.1.5:8000/api',
                isDense: true,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () async {
              await AppConfig.setRuntimeOverride(null);
              if (dialogContext.mounted) Navigator.of(dialogContext).pop();
            },
            child: const Text('Reset ke Default'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () async {
              await AppConfig.setRuntimeOverride(controller.text);
              if (dialogContext.mounted) Navigator.of(dialogContext).pop();
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  InputDecoration _decoration(String label, IconData icon, {String? hint, Widget? suffixIcon}) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon, size: 20),
      suffixIcon: suffixIcon,
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight - 32),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 440),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Align(
                          alignment: Alignment.topRight,
                          child: IconButton(
                            icon: Icon(Icons.settings_ethernet_rounded, color: AppTheme.muted.withValues(alpha: 0.6), size: 20),
                            tooltip: 'Pengaturan Server',
                            onPressed: _showServerSettingsDialog,
                          ),
                        ),
                        const SizedBox(height: 8),

                        // Lockup logo -- badge kecil bergradasi brand, bukan
                        // hero warna-warni full-bleed, kesannya lebih rapi &
                        // profesional (mirip app fintech/SaaS enterprise).
                        Center(
                          child: Container(
                            width: 84,
                            height: 84,
                            decoration: BoxDecoration(
                              gradient: AppTheme.primaryGradient,
                              borderRadius: BorderRadius.circular(24),
                              boxShadow: AppTheme.glowShadow(AppTheme.primary, opacity: 0.28),
                            ),
                            padding: const EdgeInsets.all(4),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(20),
                              child: Image.asset('assets/images/logo_icon.png', fit: BoxFit.cover),
                            ),
                          ).animate().scale(begin: const Offset(0.75, 0.75), curve: Curves.easeOutBack, duration: 420.ms).fadeIn(duration: 280.ms),
                        ),
                        const SizedBox(height: 20),
                        Text(
                          'KosKita',
                          textAlign: TextAlign.center,
                          style: GoogleFonts.plusJakartaSans(fontSize: 24, fontWeight: FontWeight.w800, color: Theme.of(context).textTheme.headlineSmall?.color, letterSpacing: 0.2),
                        ),
                        const SizedBox(height: 6),
                        AnimatedSwitcher(
                          duration: const Duration(milliseconds: 220),
                          child: Text(
                            _isLogin ? 'Masuk untuk lanjut cari kos idamanmu' : 'Daftar, gratis kurang dari semenit',
                            key: ValueKey(_isLogin),
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                        const SizedBox(height: 28),

                        Container(
                          padding: const EdgeInsets.all(22),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(color: isDark ? Colors.white.withValues(alpha: 0.06) : const Color(0xFFEEF1F6)),
                            boxShadow: AppTheme.softShadow(opacity: 0.06),
                          ),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                AnimatedSize(
                                  duration: const Duration(milliseconds: 260),
                                  curve: Curves.easeOut,
                                  alignment: Alignment.topCenter,
                                  child: !_isLogin
                                      ? Column(
                                          crossAxisAlignment: CrossAxisAlignment.stretch,
                                          children: [
                                            TextFormField(
                                              controller: _nameController,
                                              textInputAction: TextInputAction.next,
                                              decoration: _decoration('Nama Lengkap', Icons.person_outline_rounded),
                                              validator: (value) => value == null || value.isEmpty ? 'Nama harus diisi' : null,
                                            ),
                                            const SizedBox(height: 14),
                                            const Text('Daftar sebagai', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                                            const SizedBox(height: 8),
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: _RoleCard(
                                                    icon: Icons.search_rounded,
                                                    label: 'Penyewa Kos',
                                                    description: 'Cari & booking kos',
                                                    selected: _role == 'user',
                                                    onTap: () => setState(() => _role = 'user'),
                                                  ),
                                                ),
                                                const SizedBox(width: 12),
                                                Expanded(
                                                  child: _RoleCard(
                                                    icon: Icons.home_work_rounded,
                                                    label: 'Penyedia Kos',
                                                    description: 'Kelola & sewakan kos',
                                                    selected: _role == 'owner',
                                                    onTap: () => setState(() => _role = 'owner'),
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 14),
                                          ],
                                        )
                                      : const SizedBox(width: double.infinity),
                                ),
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  decoration: _decoration('Alamat Email', Icons.email_outlined),
                                  validator: (value) => value == null || !value.contains('@') ? 'Email tidak valid' : null,
                                ),
                                AnimatedSize(
                                  duration: const Duration(milliseconds: 260),
                                  curve: Curves.easeOut,
                                  alignment: Alignment.topCenter,
                                  child: !_isLogin
                                      ? Padding(
                                          padding: const EdgeInsets.only(top: 14),
                                          child: TextFormField(
                                            controller: _phoneController,
                                            keyboardType: TextInputType.phone,
                                            textInputAction: TextInputAction.next,
                                            decoration: _decoration('Nomor HP', Icons.phone_outlined, hint: '08xxxxxxxxxx'),
                                            validator: (value) {
                                              if (value == null || value.isEmpty) {
                                                return 'Nomor HP harus diisi';
                                              }
                                              if (!_phoneRegex.hasMatch(value)) {
                                                return 'Format nomor HP tidak valid';
                                              }
                                              return null;
                                            },
                                          ),
                                        )
                                      : const SizedBox(width: double.infinity),
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  textInputAction: TextInputAction.done,
                                  onFieldSubmitted: (_) => _submit(),
                                  decoration: _decoration(
                                    'Password',
                                    Icons.lock_outline_rounded,
                                    suffixIcon: IconButton(
                                      icon: Icon(
                                        _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                                        size: 20,
                                        color: AppTheme.muted,
                                      ),
                                      tooltip: _obscurePassword ? 'Tampilkan password' : 'Sembunyikan password',
                                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                    ),
                                  ),
                                  validator: (value) => value == null || value.length < 6 ? 'Password minimal 6 karakter' : null,
                                ),
                                AnimatedSize(
                                  duration: const Duration(milliseconds: 260),
                                  curve: Curves.easeOut,
                                  alignment: Alignment.topCenter,
                                  child: !_isLogin
                                      ? Padding(
                                          padding: const EdgeInsets.only(top: 10),
                                          child: Row(
                                            crossAxisAlignment: CrossAxisAlignment.center,
                                            children: [
                                              Checkbox(
                                                value: _agreedToTerms,
                                                onChanged: (v) => setState(() => _agreedToTerms = v ?? false),
                                                activeColor: AppTheme.primary,
                                              ),
                                              Expanded(
                                                child: Wrap(
                                                  children: [
                                                    const Text('Saya setuju dengan ', style: TextStyle(fontSize: 12.5)),
                                                    GestureDetector(
                                                      onTap: () => openLegalPage(context, '/terms'),
                                                      child: const Text(
                                                        'Syarat & Ketentuan',
                                                        style: TextStyle(fontSize: 12.5, color: AppTheme.primary, fontWeight: FontWeight.w600),
                                                      ),
                                                    ),
                                                    const Text(' dan ', style: TextStyle(fontSize: 12.5)),
                                                    GestureDetector(
                                                      onTap: () => openLegalPage(context, '/privacy'),
                                                      child: const Text(
                                                        'Kebijakan Privasi',
                                                        style: TextStyle(fontSize: 12.5, color: AppTheme.primary, fontWeight: FontWeight.w600),
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ],
                                          ),
                                        )
                                      : const SizedBox(width: double.infinity),
                                ),
                                const SizedBox(height: 22),

                                PremiumButton(
                                  label: _isLogin ? 'Masuk' : 'Daftar',
                                  loading: authProvider.isLoading,
                                  onPressed: authProvider.isLoading ? null : _submit,
                                ),

                                if (_googleConfigured) ...[
                                  const SizedBox(height: 18),
                                  Row(
                                    children: [
                                      const Expanded(child: Divider()),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 10),
                                        child: Text('atau', style: TextStyle(color: AppTheme.muted, fontSize: 12.5)),
                                      ),
                                      const Expanded(child: Divider()),
                                    ],
                                  ),
                                  const SizedBox(height: 14),
                                  OutlinedButton.icon(
                                    onPressed: _isGoogleLoading ? null : _submitGoogle,
                                    icon: _isGoogleLoading
                                        ? const SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(strokeWidth: 2),
                                          )
                                        : const Icon(Icons.g_mobiledata_rounded, size: 26),
                                    label: Text(_isLogin ? 'Masuk dengan Google' : 'Daftar dengan Google'),
                                    style: OutlinedButton.styleFrom(
                                      minimumSize: const Size(double.infinity, 50),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 14),

                                Center(
                                  child: TextButton(
                                    onPressed: () => setState(() => _isLogin = !_isLogin),
                                    child: Text(
                                      _isLogin ? 'Belum punya akun? Daftar sekarang' : 'Sudah punya akun? Masuk di sini',
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ).animate().fadeIn(delay: 80.ms, duration: 320.ms).slideY(begin: 0.04, end: 0),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Kartu pilihan peran registrasi -- sengaja dibuat bukan ChoiceChip biasa
/// supaya jelas belum ada yang "terpilih" secara visual sampai pengguna
/// benar-benar menyentuhnya. Badge centang muncul di pojok saat terpilih.
class _RoleCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String description;
  final bool selected;
  final VoidCallback onTap;

  const _RoleCard({
    required this.icon,
    required this.label,
    required this.description,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
        decoration: BoxDecoration(
          color: selected ? AppTheme.primary.withValues(alpha: 0.07) : Theme.of(context).inputDecorationTheme.fillColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? AppTheme.primary : Colors.transparent,
            width: selected ? 1.6 : 1,
          ),
        ),
        child: Stack(
          clipBehavior: Clip.none,
          // Stack defaultnya menempel ke kiri-atas -- tanpa ini, Column
          // ikon+teks di bawah (yang cuma selebar kontennya sendiri) jadi
          // nempel di tepi kiri kartu, bukan di tengah.
          alignment: Alignment.topCenter,
          children: [
            Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    gradient: selected ? AppTheme.primaryGradient : null,
                    color: selected ? null : Colors.white.withValues(alpha: 0.6),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, color: selected ? Colors.white : const Color(0xFF94A3B8), size: 22),
                ),
                const SizedBox(height: 8),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5, color: selected ? AppTheme.primary : Theme.of(context).colorScheme.onSurface),
                ),
                const SizedBox(height: 2),
                Text(
                  description,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 10.5, color: AppTheme.muted),
                ),
              ],
            ),
            if (selected)
              Positioned(
                top: -4,
                right: -4,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(color: AppTheme.success, shape: BoxShape.circle),
                  child: const Icon(Icons.check_rounded, color: Colors.white, size: 12),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
