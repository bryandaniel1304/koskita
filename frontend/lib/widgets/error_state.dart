import 'package:flutter/material.dart';

/// Tampilan error/empty state yang seragam dipakai di seluruh app saat
/// fetch gagal atau data kosong, lengkap dengan tombol "Coba Lagi" opsional.
class ErrorStateView extends StatelessWidget {
  final String message;
  final IconData icon;
  final VoidCallback? onRetry;

  const ErrorStateView({
    super.key,
    required this.message,
    this.icon = Icons.wifi_off_rounded,
    this.onRetry,
  });

  const ErrorStateView.empty({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: Colors.grey.shade400),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey.shade600, fontSize: 15),
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Coba Lagi'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
