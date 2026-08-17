import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../providers/booking_provider.dart';
import '../models/kos.dart';
import '../config/app_theme.dart';
import '../widgets/premium_button.dart';
import '../utils/haptics.dart';

class BookingFormScreen extends StatefulWidget {
  final Kos kos;

  const BookingFormScreen({super.key, required this.kos});

  @override
  State<BookingFormScreen> createState() => _BookingFormScreenState();
}

class _BookingFormScreenState extends State<BookingFormScreen> {
  DateTime _startDate = DateTime.now().add(const Duration(days: 1));
  int _durationMonths = 1;
  final _notesController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _startDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _startDate = picked);
  }

  Future<void> _submit() async {
    setState(() => _submitting = true);
    final provider = Provider.of<BookingProvider>(context, listen: false);
    final error = await provider.createBooking(
      kosId: widget.kos.id,
      startDate: _startDate,
      durationMonths: _durationMonths,
      notes: _notesController.text.trim(),
    );
    if (!mounted) return;
    setState(() => _submitting = false);

    if (error == null) {
      Haptics.success();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pengajuan booking terkirim! Menunggu konfirmasi admin.'),
          backgroundColor: AppTheme.success,
        ),
      );
      Navigator.of(context).pop();
    } else {
      Haptics.error();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error), backgroundColor: AppTheme.danger),
      );
    }
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(text, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
      );

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('d MMMM yyyy', 'id_ID');

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: const Text('Ajukan Booking')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: Theme.of(context).dividerTheme.color ?? Colors.transparent),
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: CachedNetworkImage(imageUrl: widget.kos.coverImage, width: 56, height: 56, fit: BoxFit.cover),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(widget.kos.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 3),
                        Text(widget.kos.location, style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 26),
            _label('Tanggal Mulai Sewa'),
            InkWell(
              onTap: _pickDate,
              borderRadius: BorderRadius.circular(16),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Theme.of(context).inputDecorationTheme.fillColor,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today_rounded, size: 18, color: AppTheme.primary),
                    const SizedBox(width: 12),
                    Text(dateFormat.format(_startDate), style: const TextStyle(fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 22),
            _label('Durasi Sewa'),
            DropdownButtonFormField<int>(
              initialValue: _durationMonths,
              items: List.generate(12, (i) => i + 1).map((m) => DropdownMenuItem(value: m, child: Text('$m bulan'))).toList(),
              onChanged: (val) => setState(() => _durationMonths = val ?? 1),
            ),
            const SizedBox(height: 22),
            _label('Catatan (opsional)'),
            TextField(
              controller: _notesController,
              maxLines: 3,
              decoration: const InputDecoration(hintText: 'Mis. rencana pindah tanggal berapa, pertanyaan untuk admin, dll.'),
            ),
            const SizedBox(height: 30),
            PremiumButton(
              label: 'Kirim Pengajuan',
              icon: Icons.send_rounded,
              loading: _submitting,
              onPressed: _submitting ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}
