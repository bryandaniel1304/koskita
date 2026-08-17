import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/app_config.dart';
import 'api_exception.dart';

class ApiService {
  // Mesin dev backend ini punya jeda bootstrap PHP yang cukup tinggi
  // (~10-15 detik per request), ditambah endpoint yang benar-benar kirim
  // email (SMTP) butuh waktu tambahan -- 12 detik lama sering timeout
  // padahal request-nya sebenarnya berhasil di server.
  static const _timeout = Duration(seconds: 30);
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';

  /// Dipanggil setiap kali request mendapat 401, supaya AuthProvider bisa
  /// membersihkan sesi & memicu redirect ke login tanpa KosProvider/
  /// BookingProvider perlu tahu apa-apa soal AuthProvider.
  static void Function()? onUnauthorized;

  static String get baseUrl => AppConfig.apiBaseUrl;

  static Future<String?> getToken() => _storage.read(key: _tokenKey);

  static Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  static Future<void> removeToken() => _storage.delete(key: _tokenKey);

  static Future<Map<String, String>> getHeaders() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  static Future<http.Response> get(String endpoint) async {
    return _handle(() async {
      final headers = await getHeaders();
      return http.get(Uri.parse('$baseUrl$endpoint'), headers: headers).timeout(_timeout);
    });
  }

  static Future<http.Response> post(String endpoint, Map<String, dynamic> body) async {
    return _handle(() async {
      final headers = await getHeaders();
      return http
          .post(Uri.parse('$baseUrl$endpoint'), headers: headers, body: jsonEncode(body))
          .timeout(_timeout);
    });
  }

  static Future<http.Response> put(String endpoint, Map<String, dynamic> body) async {
    return _handle(() async {
      final headers = await getHeaders();
      return http
          .put(Uri.parse('$baseUrl$endpoint'), headers: headers, body: jsonEncode(body))
          .timeout(_timeout);
    });
  }

  /// [body] opsional -- kebanyakan endpoint DELETE tidak butuh apa-apa
  /// selain path (mis. `/owner/qris`), tapi beberapa (mis. `/fcm-token`,
  /// yang perlu tahu token perangkat MANA yang mau dilepas) butuh
  /// menyertakan data lewat body, sama seperti Laravel menerima DELETE
  /// dengan body JSON selama Content-Type-nya sesuai.
  static Future<http.Response> delete(String endpoint, [Map<String, dynamic>? body]) async {
    return _handle(() async {
      final headers = await getHeaders();
      return http
          .delete(Uri.parse('$baseUrl$endpoint'), headers: headers, body: body != null ? jsonEncode(body) : null)
          .timeout(_timeout);
    });
  }

  /// Request multipart/form-data untuk endpoint yang menerima upload foto
  /// (form kos milik pemilik). [method] "PUT" dikirim sebagai POST + field
  /// `_method=PUT` (method spoofing Laravel) karena PHP tidak mem-parse body
  /// multipart pada request PUT asli.
  static Future<http.Response> multipart(
    String method,
    String endpoint,
    Map<String, String> fields, {
    List<XFile> photos = const [],
    String fileFieldName = 'photos[]',
  }) async {
    return _handle(() async {
      final headers = await getHeaders();
      headers.remove('Content-Type');
      final request = http.MultipartRequest('POST', Uri.parse('$baseUrl$endpoint'))
        ..headers.addAll(headers);
      if (method.toUpperCase() != 'POST') {
        fields['_method'] = method.toUpperCase();
      }
      request.fields.addAll(fields);
      for (final file in photos) {
        final bytes = await file.readAsBytes();
        request.files.add(http.MultipartFile.fromBytes(fileFieldName, bytes, filename: file.name));
      }
      final streamed = await request.send().timeout(_timeout);
      return http.Response.fromStream(streamed);
    });
  }

  /// Menjalankan request, menerjemahkan kegagalan jaringan/timeout/HTTP jadi
  /// [ApiException] yang jelas jenisnya, supaya UI bisa menampilkan pesan &
  /// aksi yang tepat alih-alih layar kosong tanpa penjelasan.
  static Future<http.Response> _handle(Future<http.Response> Function() request) async {
    http.Response response;
    try {
      response = await request();
    } on SocketException {
      throw ApiException.fromType(ApiErrorType.network);
    } on HttpException {
      throw ApiException.fromType(ApiErrorType.network);
    } catch (e) {
      if (e.toString().contains('TimeoutException')) {
        throw ApiException.fromType(ApiErrorType.timeout);
      }
      throw ApiException.fromType(ApiErrorType.network);
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response;
    }

    if (response.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException.fromType(ApiErrorType.unauthorized);
    }

    if (response.statusCode == 422) {
      final decoded = _tryDecode(response.body);
      throw ApiException(
        ApiErrorType.validation,
        decoded?['message'] ?? 'Data yang dikirim tidak valid.',
        errors: decoded?['errors'],
      );
    }

    if (response.statusCode >= 500) {
      throw ApiException.fromType(ApiErrorType.server);
    }

    final decoded = _tryDecode(response.body);
    throw ApiException(ApiErrorType.unknown, decoded?['message'] ?? 'Terjadi kesalahan (${response.statusCode}).');
  }

  static Map<String, dynamic>? _tryDecode(String body) {
    try {
      final decoded = jsonDecode(body);
      return decoded is Map<String, dynamic> ? decoded : null;
    } catch (_) {
      return null;
    }
  }
}
