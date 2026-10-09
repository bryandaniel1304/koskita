import 'dart:io';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

/// Pratinjau foto hasil image_picker sebelum diunggah. Di versi web
/// (coba.koskita) tidak ada berkas lokal -- `XFile.path` berisi blob URL,
/// jadi dimuat lewat Image.network; di HP tetap Image.file seperti biasa.
class PickedImage extends StatelessWidget {
  final XFile file;
  final double? width;
  final double? height;
  final BoxFit fit;

  const PickedImage(this.file, {super.key, this.width, this.height, this.fit = BoxFit.cover});

  @override
  Widget build(BuildContext context) {
    if (kIsWeb) {
      return Image.network(file.path, width: width, height: height, fit: fit);
    }
    return Image.file(File(file.path), width: width, height: height, fit: fit);
  }
}
