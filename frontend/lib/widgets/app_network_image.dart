import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';

/// Gambar dari jaringan yang aman untuk versi web (coba.koskita).
///
/// Di Android/iOS tetap CachedNetworkImage seperti sebelumnya. Di web paket
/// itu bermasalah di renderer CanvasKit: mode bawaannya (HtmlImage) membuat
/// foto jadi hitam/putih setelah pindah halaman, sedangkan mode HttpGet
/// gagal total kalau browser menolak CORS -- mis. Safari yang memakai salinan
/// foto dari cache lama tanpa header CORS. Image.network dengan strategi
/// fallback mencoba fetch biasa dulu, lalu otomatis menampilkan elemen <img>
/// kalau CORS ditolak, jadi foto selalu tampil. Cache-nya cache HTTP browser.
class AppNetworkImage extends StatelessWidget {
  final String imageUrl;
  final BoxFit? fit;
  final double? width;
  final double? height;
  final PlaceholderWidgetBuilder? placeholder;
  final LoadingErrorWidgetBuilder? errorWidget;

  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    this.fit,
    this.width,
    this.height,
    this.placeholder,
    this.errorWidget,
  });

  @override
  Widget build(BuildContext context) {
    if (kIsWeb) {
      return Image.network(
        imageUrl,
        fit: fit,
        width: width,
        height: height,
        webHtmlElementStrategy: WebHtmlElementStrategy.fallback,
        loadingBuilder: placeholder == null
            ? null
            : (context, child, progress) => progress == null ? child : placeholder!(context, imageUrl),
        errorBuilder: (context, error, stackTrace) =>
            errorWidget?.call(context, imageUrl, error) ?? const Icon(Icons.error),
      );
    }
    return CachedNetworkImage(
      imageUrl: imageUrl,
      fit: fit,
      width: width,
      height: height,
      placeholder: placeholder,
      errorWidget: errorWidget,
    );
  }
}
