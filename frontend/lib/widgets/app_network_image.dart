import 'package:cached_network_image/cached_network_image.dart';
import 'package:cached_network_image_platform_interface/cached_network_image_platform_interface.dart'
    show ImageRenderMethodForWeb;
import 'package:flutter/material.dart';

/// CachedNetworkImage dengan cara muat yang aman untuk versi web.
///
/// Bawaan paketnya di web memuat gambar lewat elemen `<img>` (HtmlImage).
/// Di renderer CanvasKit, gambar yang dimuat begitu berubah jadi hitam/putih
/// setelah pindah halaman (mis. buka detail kos lalu kembali ke Beranda) --
/// HttpGet mengunduh byte gambarnya sendiri sehingga tetap tampil. Di
/// Android/iOS parameter ini diabaikan, perilakunya sama seperti sebelumnya.
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
    return CachedNetworkImage(
      imageUrl: imageUrl,
      fit: fit,
      width: width,
      height: height,
      placeholder: placeholder,
      errorWidget: errorWidget,
      imageRenderMethodForWeb: ImageRenderMethodForWeb.HttpGet,
    );
  }
}
