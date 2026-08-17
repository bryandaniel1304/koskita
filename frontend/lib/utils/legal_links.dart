import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/app_config.dart';

/// Buka Kebijakan Privasi/Syarat & Ketentuan lewat browser eksternal --
/// dokumen HTML biasa dari backend (routes/web.php: /privacy, /terms),
/// bukan endpoint API.
Future<void> openLegalPage(BuildContext context, String path) async {
  final uri = Uri.parse('${AppConfig.webBaseUrl}$path');
  final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
  if (!launched && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Tidak bisa membuka halaman ini.')),
    );
  }
}
