enum ApiErrorType { network, timeout, unauthorized, validation, server, unknown }

class ApiException implements Exception {
  final ApiErrorType type;
  final String message;
  final Map<String, dynamic>? errors;

  ApiException(this.type, this.message, {this.errors});

  factory ApiException.fromType(ApiErrorType type) {
    switch (type) {
      case ApiErrorType.network:
        return ApiException(type, 'Tidak bisa terhubung ke server. Periksa koneksi internet kamu.');
      case ApiErrorType.timeout:
        return ApiException(type, 'Server tidak merespons. Coba lagi beberapa saat lagi.');
      case ApiErrorType.unauthorized:
        return ApiException(type, 'Sesi kamu sudah berakhir. Silakan login kembali.');
      case ApiErrorType.server:
        return ApiException(type, 'Terjadi kesalahan pada server. Coba lagi nanti.');
      case ApiErrorType.validation:
        return ApiException(type, 'Data yang dikirim tidak valid.');
      case ApiErrorType.unknown:
        return ApiException(type, 'Terjadi kesalahan yang tidak diketahui.');
    }
  }

  @override
  String toString() => message;
}
