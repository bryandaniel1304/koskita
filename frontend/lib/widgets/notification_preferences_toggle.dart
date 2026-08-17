import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../config/app_theme.dart';

/// Tiga switch "Pengaturan Notifikasi" -- dipakai di layar Profil penyewa
/// maupun pemilik. Satu switch = mematikan email DAN push (FCM) sekaligus
/// untuk jenis itu, lihat AuthProvider.updateNotificationPreferences().
class NotificationPreferencesToggle extends StatefulWidget {
  const NotificationPreferencesToggle({super.key});

  @override
  State<NotificationPreferencesToggle> createState() => _NotificationPreferencesToggleState();
}

class _NotificationPreferencesToggleState extends State<NotificationPreferencesToggle> {
  bool _busy = false;

  Future<void> _update(AuthProvider authProvider, {bool? bookings, bool? messages, bool? waitlist}) async {
    final user = authProvider.user;
    if (user == null) return;

    setState(() => _busy = true);
    final ok = await authProvider.updateNotificationPreferences(
      notifyBookings: bookings ?? user.notifyBookings,
      notifyMessages: messages ?? user.notifyMessages,
      notifyWaitlist: waitlist ?? user.notifyWaitlist,
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal menyimpan preferensi. Coba lagi.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.user;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          secondary: const Icon(Icons.calendar_month_rounded, color: AppTheme.primary),
          title: const Text('Status Booking', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
          subtitle: const Text('Dikonfirmasi/ditolak/selesai', style: TextStyle(fontSize: 11.5)),
          value: user?.notifyBookings ?? true,
          onChanged: _busy ? null : (value) => _update(authProvider, bookings: value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          secondary: const Icon(Icons.chat_bubble_rounded, color: AppTheme.primary),
          title: const Text('Pesan Baru', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
          value: user?.notifyMessages ?? true,
          onChanged: _busy ? null : (value) => _update(authProvider, messages: value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          secondary: const Icon(Icons.hourglass_bottom_rounded, color: AppTheme.primary),
          title: const Text('Kamar Tersedia Lagi', style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600)),
          subtitle: const Text('Daftar tunggu kos yang penuh', style: TextStyle(fontSize: 11.5)),
          value: user?.notifyWaitlist ?? true,
          onChanged: _busy ? null : (value) => _update(authProvider, waitlist: value),
        ),
      ],
    );
  }
}
