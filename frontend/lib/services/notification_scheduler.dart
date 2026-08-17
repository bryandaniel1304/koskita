import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:timezone/data/latest.dart' as tz_data;
import '../models/booking.dart';

/// Pengingat sewa terjadwal -- dijadwalkan & disimpan sepenuhnya DI
/// PERANGKAT (bukan lewat server/push), jadi tidak butuh akun pihak
/// ketiga apa pun (beda dari push notification sungguhan/FCM yang masih
/// terhalang akun Firebase). Begitu booking dikonfirmasi, jadwalkan
/// notifikasi H-3 sebelum tanggal jatuh tempo bulanan berikutnya --
/// perhitungan tanggalnya SENGAJA sama persis dengan
/// NotificationController::rentReminders() di backend, supaya pesan dalam
/// notifikasi HP dan feed notifikasi dalam-app tidak pernah berbeda.
class NotificationScheduler {
  static final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  /// ID notifikasi rentang 90000-90999 dikhususkan untuk pengingat sewa
  /// (90000 + bookingId % 1000) -- rentang terpisah dari ID notifikasi lain
  /// supaya tidak pernah tabrakan/saling timpa.
  static int _reminderId(int bookingId) => 90000 + (bookingId % 1000);

  static Future<void> initialize() async {
    if (_initialized) return;
    try {
      tz_data.initializeTimeZones();
      // Perangkat Indonesia -- disamakan dengan zona waktu server (WIB)
      // supaya perhitungan H-3 tidak geser gara-gara beda zona waktu.
      tz.setLocalLocation(tz.getLocation('Asia/Jakarta'));

      const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
      const iosSettings = DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      );
      await _plugin.initialize(
        const InitializationSettings(android: androidSettings, iOS: iosSettings),
      );
      _initialized = true;
    } catch (e) {
      // Gagal inisialisasi (mis. platform tidak didukung/testing) tidak
      // boleh menjatuhkan seluruh app -- pengingat cuma pelengkap.
      debugPrint('NotificationScheduler init gagal: $e');
    }
  }

  /// Minta izin notifikasi -- WAJIB diminta eksplisit di Android 13+ &
  /// iOS. Dipanggil sekali habis login, bukan langsung saat app dibuka,
  /// supaya konteksnya jelas buat pengguna (bukan izin acak di awal).
  static Future<bool> requestPermission() async {
    try {
      final androidImpl = _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      final iosImpl = _plugin.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();

      final androidGranted = await androidImpl?.requestNotificationsPermission();
      final iosGranted = await iosImpl?.requestPermissions(alert: true, badge: true, sound: true);

      return (androidGranted ?? true) || (iosGranted ?? true);
    } catch (e) {
      debugPrint('NotificationScheduler requestPermission gagal: $e');
      return false;
    }
  }

  /// Hitung tanggal jatuh tempo bulanan BERIKUTNYA untuk satu booking --
  /// identik dengan logika PHP di NotificationController::rentReminders().
  /// Null kalau masa sewa (duration_months) sudah habis.
  static DateTime? _nextDueDate(Booking booking) {
    final today = DateTime.now();
    final todayMidnight = DateTime(today.year, today.month, today.day);
    var due = DateTime(booking.startDate.year, booking.startDate.month, booking.startDate.day);
    var cycle = 0;

    while (due.isBefore(todayMidnight)) {
      due = DateTime(due.year, due.month + 1, due.day);
      cycle++;
    }

    if (cycle >= booking.durationMonths) return null;
    return due;
  }

  /// Jadwalkan (atau jadwalkan ulang) pengingat H-3 untuk satu booking
  /// confirmed. Aman dipanggil berulang -- ID stabil per booking, jadi
  /// panggilan berikutnya otomatis menimpa jadwal lama, bukan menumpuk
  /// notifikasi duplikat.
  static Future<void> scheduleRentReminder(Booking booking) async {
    if (!_initialized || booking.status != 'confirmed') return;

    final dueDate = _nextDueDate(booking);
    if (dueDate == null) {
      await cancelRentReminder(booking.id);
      return;
    }

    final reminderDate = dueDate.subtract(const Duration(days: 3));
    final now = DateTime.now();
    if (reminderDate.isBefore(now)) {
      // Kalau H-3 sudah lewat (mis. baru buka app 1 hari sebelum jatuh
      // tempo), jangan jadwalkan ke masa lalu -- flutter_local_notifications
      // menolak jadwal yang sudah lewat.
      return;
    }

    try {
      final scheduledTz = tz.TZDateTime(tz.local, reminderDate.year, reminderDate.month, reminderDate.day, 9, 0);
      final kosName = booking.kos?.name ?? 'kos kamu';

      await _plugin.zonedSchedule(
        _reminderId(booking.id),
        'Pengingat Sewa Bulanan',
        'Waktunya bayar sewa "$kosName" dalam 3 hari (${_formatDate(dueDate)}).',
        scheduledTz,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'rent_reminders',
            'Pengingat Sewa',
            channelDescription: 'Pengingat H-3 sebelum jatuh tempo sewa bulanan.',
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(),
        ),
        // inexact (bukan exact) SENGAJA -- pengingat H-3 tidak butuh
        // presisi menit, jadi tidak perlu minta izin sensitif "Alarms &
        // Reminders" (SCHEDULE_EXACT_ALARM) di Android 12+. Selisih
        // beberapa menit/jam dari jadwal tidak masalah untuk kasus ini.
        androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
        uiLocalNotificationDateInterpretation: UILocalNotificationDateInterpretation.absoluteTime,
      );
    } catch (e) {
      // Gagal jadwalkan (mis. izin exact-alarm belum diberikan di Android
      // 12+) tidak boleh mengganggu alur utama -- ini fitur pelengkap.
      debugPrint('NotificationScheduler scheduleRentReminder gagal: $e');
    }
  }

  static Future<void> cancelRentReminder(int bookingId) async {
    if (!_initialized) return;
    try {
      await _plugin.cancel(_reminderId(bookingId));
    } catch (_) {
      // Diam-diam -- membatalkan jadwal yang mungkin memang belum ada bukan error nyata.
    }
  }

  /// Sinkronkan jadwal pengingat dengan daftar booking terbaru dari server
  /// -- dipanggil tiap kali BookingProvider selesai fetch, supaya jadwal
  /// otomatis mengikuti booking baru dikonfirmasi/dibatalkan tanpa logika
  /// terpisah di tiap layar.
  static Future<void> syncWithBookings(List<Booking> bookings) async {
    if (!_initialized) return;
    for (final booking in bookings) {
      if (booking.status == 'confirmed') {
        await scheduleRentReminder(booking);
      } else {
        await cancelRentReminder(booking.id);
      }
    }
  }

  static String _formatDate(DateTime date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }
}
