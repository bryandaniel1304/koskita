import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Design system KOSKITA -- arah visual "Modern & Premium": tipografi tegas
/// (Plus Jakarta Sans, sama dengan brand di web/admin), banyak whitespace,
/// shadow lembut & gradient halus alih-alih warna flat polos. Warna brand
/// (#355DDB/#7091F9) tetap sama di kedua mode, cuma latar & permukaan yang
/// berubah antara light/dark.
class AppTheme {
  static const primary = Color(0xFF355DDB);
  static const primaryLight = Color(0xFF7091F9);
  static const primaryDark = Color(0xFF2137A2);
  static const danger = Color(0xFFF43F5E);
  static const success = Color(0xFF10B981);
  static const warning = Color(0xFFF59E0B);
  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);

  static const primaryGradient = LinearGradient(
    colors: [primaryLight, primary],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  /// Shadow premium standar -- lembut & menyebar, dipakai di kartu, tombol
  /// utama, dan elemen melayang (bubble chat, bottom nav) supaya konsisten.
  static List<BoxShadow> softShadow({Color? tint, double opacity = 0.08}) => [
        BoxShadow(
          color: (tint ?? ink).withValues(alpha: opacity),
          blurRadius: 24,
          offset: const Offset(0, 10),
        ),
      ];

  static List<BoxShadow> glowShadow(Color color, {double opacity = 0.35}) => [
        BoxShadow(
          color: color.withValues(alpha: opacity),
          blurRadius: 20,
          offset: const Offset(0, 8),
        ),
      ];

  static ThemeData get light => _build(Brightness.light);
  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final bg = isDark ? const Color(0xFF0B1220) : const Color(0xFFF8FAFC);
    final surface = isDark ? const Color(0xFF1A2436) : Colors.white;
    final onSurface = isDark ? const Color(0xFFE2E8F0) : ink;

    final base = ThemeData(
      brightness: brightness,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primaryLight,
        primary: isDark ? primaryLight : primary,
        secondary: danger,
        surface: surface,
        brightness: brightness,
      ),
      scaffoldBackgroundColor: bg,
      useMaterial3: true,
      cardColor: surface,
      splashFactory: InkSparkle.splashFactory,
    );

    final textTheme = GoogleFonts.plusJakartaSansTextTheme(base.textTheme).copyWith(
      displaySmall: GoogleFonts.plusJakartaSans(fontSize: 30, fontWeight: FontWeight.w800, color: onSurface, letterSpacing: -0.5),
      headlineMedium: GoogleFonts.plusJakartaSans(fontSize: 24, fontWeight: FontWeight.w800, color: onSurface, letterSpacing: -0.4),
      headlineSmall: GoogleFonts.plusJakartaSans(fontSize: 20, fontWeight: FontWeight.w800, color: onSurface, letterSpacing: -0.3),
      titleLarge: GoogleFonts.plusJakartaSans(fontSize: 18, fontWeight: FontWeight.w700, color: onSurface),
      titleMedium: GoogleFonts.plusJakartaSans(fontSize: 15, fontWeight: FontWeight.w700, color: onSurface),
      titleSmall: GoogleFonts.plusJakartaSans(fontSize: 13, fontWeight: FontWeight.w700, color: onSurface),
      bodyLarge: GoogleFonts.plusJakartaSans(fontSize: 15, fontWeight: FontWeight.w500, color: onSurface, height: 1.5),
      bodyMedium: GoogleFonts.plusJakartaSans(fontSize: 13.5, fontWeight: FontWeight.w500, color: isDark ? const Color(0xFF94A3B8) : muted, height: 1.5),
      bodySmall: GoogleFonts.plusJakartaSans(fontSize: 12, fontWeight: FontWeight.w500, color: isDark ? const Color(0xFF94A3B8) : muted),
      labelLarge: GoogleFonts.plusJakartaSans(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 0.1),
    );

    return base.copyWith(
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: onSurface,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: GoogleFonts.plusJakartaSans(fontSize: 17, fontWeight: FontWeight.w800, color: onSurface),
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: isDark ? Colors.white.withValues(alpha: 0.06) : const Color(0xFFEEF1F6)),
        ),
      ),
      dividerTheme: DividerThemeData(color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFEEF1F6), thickness: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF141C2C) : const Color(0xFFF4F6FA),
        contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        hintStyle: TextStyle(color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8), fontWeight: FontWeight.w500),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: primaryLight, width: 1.6)),
        errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: danger, width: 1.4)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: danger, width: 1.6)),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: isDark ? const Color(0xFF283245) : const Color(0xFFE2E8F0),
          padding: const EdgeInsets.symmetric(vertical: 16),
          elevation: 0,
          textStyle: GoogleFonts.plusJakartaSans(fontSize: 15, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: onSurface,
          padding: const EdgeInsets.symmetric(vertical: 16),
          side: BorderSide(color: isDark ? Colors.white.withValues(alpha: 0.12) : const Color(0xFFE2E8F0)),
          textStyle: GoogleFonts.plusJakartaSans(fontSize: 15, fontWeight: FontWeight.w700),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          textStyle: GoogleFonts.plusJakartaSans(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: isDark ? const Color(0xFF141C2C) : const Color(0xFFF4F6FA),
        selectedColor: primary,
        labelStyle: GoogleFonts.plusJakartaSans(fontSize: 12.5, fontWeight: FontWeight.w700, color: onSurface),
        secondaryLabelStyle: GoogleFonts.plusJakartaSans(fontSize: 12.5, fontWeight: FontWeight.w700, color: Colors.white),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        shape: StadiumBorder(side: BorderSide(color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0))),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: isDark ? const Color(0xFF1E293B) : ink,
        contentTextStyle: GoogleFonts.plusJakartaSans(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        insetPadding: const EdgeInsets.all(16),
      ),
    );
  }
}
