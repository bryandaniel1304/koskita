import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../providers/owner_kos_provider.dart';
import '../../models/kos.dart';
import '../../models/kos_room_type.dart';
import '../../config/app_theme.dart';
import '../../widgets/premium_button.dart';
import '../../widgets/location_picker_field.dart';

class OwnerKosFormScreen extends StatefulWidget {
  final Kos? kos;

  const OwnerKosFormScreen({super.key, this.kos});

  @override
  State<OwnerKosFormScreen> createState() => _OwnerKosFormScreenState();
}

class _OwnerKosFormScreenState extends State<OwnerKosFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(text: widget.kos?.name ?? '');
  late final _priceController = TextEditingController(text: widget.kos?.price.toString() ?? '');
  late final _totalRoomsController = TextEditingController(text: widget.kos?.totalRooms.toString() ?? '1');
  late final _locationController = TextEditingController(text: widget.kos?.location ?? '');
  late final _distanceController = TextEditingController(text: widget.kos?.distanceToCampus.toString() ?? '');
  late final _descriptionController = TextEditingController(text: widget.kos?.description ?? '');

  double? _pickedLatitude;
  double? _pickedLongitude;
  String? _genderType;
  final Set<int> _selectedFacilityIds = {};
  final Set<int> _selectedRuleIds = {};
  final List<XFile> _newPhotos = [];
  bool _isSaving = false;

  /// Salinan lokal (BUKAN widget.kos!.roomTypes langsung) -- widget.kos
  /// tidak pernah berubah setelah layar ini dibuka (immutable, dilewat
  /// sekali lewat konstruktor), jadi tambah/ubah/hapus tipe kamar butuh
  /// state sendiri di sini supaya langsung kelihatan di UI tanpa perlu
  /// keluar-masuk layar dulu.
  late List<KosRoomType> _roomTypes;

  bool get _isEdit => widget.kos != null;

  @override
  void initState() {
    super.initState();
    _genderType = widget.kos?.genderType.isNotEmpty == true ? widget.kos!.genderType : null;
    _pickedLatitude = widget.kos?.latitude;
    _pickedLongitude = widget.kos?.longitude;
    if (widget.kos != null) {
      _selectedFacilityIds.addAll(widget.kos!.facilities.map((f) => f.id));
      _selectedRuleIds.addAll(widget.kos!.rules.map((r) => r.id));
    }
    _roomTypes = List.of(widget.kos?.roomTypes ?? const []);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _priceController.dispose();
    _totalRoomsController.dispose();
    _locationController.dispose();
    _distanceController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _pickPhotos() async {
    final picked = await ImagePicker().pickMultiImage(imageQuality: 80);
    if (picked.isEmpty) return;
    setState(() => _newPhotos.addAll(picked));
  }

  Future<void> _deleteExistingImage(int imageId) async {
    if (widget.kos == null) return;
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    await provider.deleteImage(widget.kos!.id, imageId);
    if (!mounted) return;
    setState(() {});
  }

  /// Breakdown tipe kamar OPSIONAL -- cuma bisa dikelola kalau kos-nya
  /// sudah ada (butuh kos_id sungguhan buat dilekatkan), makanya section
  /// ini di UI cuma tampil saat _isEdit.
  Future<void> _showRoomTypeDialog({KosRoomType? existing}) async {
    final nameController = TextEditingController(text: existing?.name ?? '');
    final priceController = TextEditingController(text: existing?.price.toString() ?? '');
    final roomsController = TextEditingController(text: existing?.totalRooms.toString() ?? '');
    final formKey = GlobalKey<FormState>();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(existing == null ? 'Tambah Tipe Kamar' : 'Ubah Tipe Kamar'),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: nameController,
                decoration: const InputDecoration(labelText: 'Nama Tipe (mis. Kamar AC)'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: priceController,
                decoration: const InputDecoration(labelText: 'Harga per Bulan (Rp)'),
                keyboardType: TextInputType.number,
                validator: (v) => (int.tryParse(v ?? '') == null) ? 'Harus angka' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: roomsController,
                decoration: const InputDecoration(labelText: 'Jumlah Kamar'),
                keyboardType: TextInputType.number,
                validator: (v) => (int.tryParse(v ?? '') == null) ? 'Harus angka' : null,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () {
              if (formKey.currentState!.validate()) Navigator.pop(dialogContext, true);
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final name = nameController.text.trim();
    final price = int.parse(priceController.text.trim());
    final rooms = int.parse(roomsController.text.trim());

    final saved = existing == null
        ? await provider.addRoomType(widget.kos!.id, name: name, price: price, totalRooms: rooms)
        : await provider.updateRoomType(widget.kos!.id, existing.id, name: name, price: price, totalRooms: rooms);

    if (!mounted) return;
    if (saved != null) {
      setState(() {
        if (existing == null) {
          _roomTypes = [..._roomTypes, saved];
        } else {
          _roomTypes = _roomTypes.map((t) => t.id == existing.id ? saved : t).toList();
        }
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal menyimpan tipe kamar. Coba lagi.'), backgroundColor: AppTheme.danger),
      );
    }
  }

  Future<void> _deleteRoomType(int roomTypeId) async {
    if (widget.kos == null) return;
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);
    final ok = await provider.deleteRoomType(widget.kos!.id, roomTypeId);
    if (!mounted) return;
    if (ok) {
      setState(() => _roomTypes = _roomTypes.where((t) => t.id != roomTypeId).toList());
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal menghapus tipe kamar. Coba lagi.'), backgroundColor: AppTheme.danger),
      );
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_genderType == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih tipe kos dulu.'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    final totalRooms = int.tryParse(_totalRoomsController.text.trim()) ?? 1;
    if (_isEdit && totalRooms < widget.kos!.occupiedRooms) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Jumlah kamar tidak boleh kurang dari ${widget.kos!.occupiedRooms} (yang sedang terisi).'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);
    final provider = Provider.of<OwnerKosProvider>(context, listen: false);

    final String? error;
    if (_isEdit) {
      error = await provider.updateKos(
        id: widget.kos!.id,
        name: _nameController.text.trim(),
        price: int.tryParse(_priceController.text.trim()) ?? 0,
        genderType: _genderType!,
        location: _locationController.text.trim(),
        latitude: _pickedLatitude,
        longitude: _pickedLongitude,
        distanceToCampus: double.tryParse(_distanceController.text.trim()) ?? 0,
        totalRooms: totalRooms,
        description: _descriptionController.text.trim(),
        facilityIds: _selectedFacilityIds.toList(),
        ruleIds: _selectedRuleIds.toList(),
        photos: _newPhotos,
      );
    } else {
      error = await provider.createKos(
        name: _nameController.text.trim(),
        price: int.tryParse(_priceController.text.trim()) ?? 0,
        genderType: _genderType!,
        location: _locationController.text.trim(),
        latitude: _pickedLatitude,
        longitude: _pickedLongitude,
        distanceToCampus: double.tryParse(_distanceController.text.trim()) ?? 0,
        totalRooms: totalRooms,
        description: _descriptionController.text.trim(),
        facilityIds: _selectedFacilityIds.toList(),
        ruleIds: _selectedRuleIds.toList(),
        photos: _newPhotos,
      );
    }

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (error == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_isEdit ? 'Kos berhasil diperbarui.' : 'Kos berhasil ditambahkan.')),
      );
      context.pop();
    } else {
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
    final provider = Provider.of<OwnerKosProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text(_isEdit ? 'Edit Kos' : 'Tambah Kos')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Nama Kos'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 14),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 2,
                    child: TextFormField(
                      controller: _priceController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Harga per Bulan (Rp)'),
                      validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _totalRoomsController,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        labelText: 'Jumlah Kamar',
                        helperText: _isEdit ? '${widget.kos!.occupiedRooms} terisi' : null,
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return 'Wajib diisi';
                        final n = int.tryParse(v);
                        if (n == null || n < 1) return 'Min. 1';
                        return null;
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _label('Tipe Kos'),
              Wrap(
                spacing: 8,
                children: ['putra', 'putri', 'campur'].map((g) {
                  final selected = _genderType == g;
                  return GestureDetector(
                    onTap: () => setState(() => _genderType = selected ? null : g),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 160),
                      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                      decoration: BoxDecoration(
                        gradient: selected ? AppTheme.primaryGradient : null,
                        color: selected ? null : Theme.of(context).inputDecorationTheme.fillColor,
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: Text(
                        g[0].toUpperCase() + g.substring(1),
                        style: TextStyle(color: selected ? Colors.white : AppTheme.muted, fontWeight: FontWeight.w700, fontSize: 13),
                      ),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _locationController,
                decoration: const InputDecoration(labelText: 'Lokasi / Area'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 16),
              _label('Titik Lokasi di Peta (opsional)'),
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Biar pin peta di detail kos akurat. Cari alamatnya, atau ketuk langsung titik yang benar di peta.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
              LocationPickerField(
                initialLatitude: _pickedLatitude,
                initialLongitude: _pickedLongitude,
                onChanged: (point) => setState(() {
                  _pickedLatitude = point?.latitude;
                  _pickedLongitude = point?.longitude;
                }),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _distanceController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Jarak ke Kampus (km)'),
                validator: (v) => v == null || v.isEmpty ? 'Wajib diisi' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _descriptionController,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Deskripsi', alignLabelWithHint: true),
              ),
              const SizedBox(height: 22),
              _label('Fasilitas'),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: provider.facilities.map((f) {
                  final selected = _selectedFacilityIds.contains(f.id);
                  return FilterChip(
                    label: Text(f.name),
                    selected: selected,
                    onSelected: (v) => setState(() => v ? _selectedFacilityIds.add(f.id) : _selectedFacilityIds.remove(f.id)),
                  );
                }).toList(),
              ),
              const SizedBox(height: 22),
              _label('Aturan'),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: provider.rules.map((r) {
                  final selected = _selectedRuleIds.contains(r.id);
                  return FilterChip(
                    label: Text(r.name),
                    selected: selected,
                    onSelected: (v) => setState(() => v ? _selectedRuleIds.add(r.id) : _selectedRuleIds.remove(r.id)),
                  );
                }).toList(),
              ),
              const SizedBox(height: 22),
              _label('Foto Kos'),
              if (widget.kos != null && widget.kos!.images.isNotEmpty)
                SizedBox(
                  height: 92,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: widget.kos!.images.map((img) {
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(14),
                              child: CachedNetworkImage(imageUrl: img.url, width: 92, height: 92, fit: BoxFit.cover),
                            ),
                            Positioned(
                              right: 3,
                              top: 3,
                              child: Semantics(
                                button: true,
                                label: 'Hapus foto ini',
                                child: GestureDetector(
                                  onTap: () => _deleteExistingImage(img.id),
                                  child: Container(
                                    padding: const EdgeInsets.all(3),
                                    decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                                    child: const Icon(Icons.close_rounded, size: 14, color: Colors.white),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ),
                ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ..._newPhotos.map((f) => Chip(
                        label: Text(f.name, overflow: TextOverflow.ellipsis),
                        onDeleted: () => setState(() => _newPhotos.remove(f)),
                      )),
                  ActionChip(
                    avatar: const Icon(Icons.add_photo_alternate_outlined, size: 18),
                    label: const Text('Tambah Foto'),
                    onPressed: _pickPhotos,
                  ),
                ],
              ),
              if (_isEdit) ...[
                const SizedBox(height: 22),
                _label('Tipe Kamar (Opsional)'),
                Text(
                  'Tampilkan breakdown harga per tipe kamar (mis. AC vs Standar) ke calon penyewa -- tidak memengaruhi jumlah kamar total kos ini.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 10),
                ..._roomTypes.map((type) => Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: Theme.of(context).inputDecorationTheme.fillColor,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(type.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                                Text('Rp ${type.price} -- ${type.totalRooms} kamar', style: Theme.of(context).textTheme.bodySmall),
                              ],
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.edit_outlined, size: 18),
                            tooltip: 'Ubah',
                            onPressed: () => _showRoomTypeDialog(existing: type),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline_rounded, size: 18, color: AppTheme.danger),
                            tooltip: 'Hapus',
                            onPressed: () => _deleteRoomType(type.id),
                          ),
                        ],
                      ),
                    )),
                OutlinedButton.icon(
                  onPressed: () => _showRoomTypeDialog(),
                  icon: const Icon(Icons.add_rounded, size: 18),
                  label: const Text('Tambah Tipe Kamar'),
                ),
              ],
              const SizedBox(height: 30),
              PremiumButton(
                label: _isEdit ? 'Simpan Perubahan' : 'Tambah Kos',
                icon: _isEdit ? Icons.save_rounded : Icons.add_circle_outline_rounded,
                loading: _isSaving,
                onPressed: _isSaving ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
