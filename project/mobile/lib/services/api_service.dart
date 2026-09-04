// HTTP client ke PHP REST API.
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config.dart';
import '../models/device_status.dart';

class ApiException implements Exception {
  final int statusCode;
  final String message;
  ApiException(this.statusCode, this.message);
  @override
  String toString() => message;
}

class ApiService {
  ApiService._();
  static final ApiService instance = ApiService._();

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body,
      {String? token}) async {
    final headers = {'Content-Type': 'application/json'};
    if (token != null) headers['Authorization'] = 'Bearer $token';
    final res = await http
        .post(Uri.parse('${AppConfig.apiBaseUrl}/api$path'),
            headers: headers, body: jsonEncode(body))
        .timeout(const Duration(seconds: 20));
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (json['success'] != true) {
      throw ApiException(res.statusCode, json['message'] ?? 'Error server');
    }
    return Map<String, dynamic>.from(json['data'] ?? {});
  }

  Future<Map<String, dynamic>> _get(String path, {String? token}) async {
    final headers = <String, String>{};
    if (token != null) headers['Authorization'] = 'Bearer $token';
    final res = await http
        .get(Uri.parse('${AppConfig.apiBaseUrl}/api$path'), headers: headers)
        .timeout(const Duration(seconds: 20));
    final json = jsonDecode(res.body) as Map<String, dynamic>;
    if (json['success'] != true) {
      throw ApiException(res.statusCode, json['message'] ?? 'Error server');
    }
    return Map<String, dynamic>.from(json['data'] ?? {});
  }

  Future<DeviceStatus> register({
    required String nrp,
    required String deviceUuid,
    required String platform,
    required String model,
  }) async {
    final data = await _post('/device/register', {
      'nrp': nrp,
      'device_uuid': deviceUuid,
      'platform': platform,
      'model': model,
      'app_version': AppConfig.appVersion,
    });
    return DeviceStatus.fromJson(data);
  }

  Future<DeviceStatus> statusByUuid(String deviceUuid) async {
    final data = await _get('/device/status?device_uuid=$deviceUuid');
    return DeviceStatus.fromJson(data);
  }

  Future<DeviceStatus> statusByToken(String token, {int? battery}) async {
    final q = battery != null ? '?battery=$battery' : '';
    final data = await _get('/device/status$q', token: token);
    return DeviceStatus.fromJson(data);
  }

  // Batch sync GPS. Response: received/inserted/duplicated/failed.
  Future<Map<String, dynamic>> syncLocations(String token, List<Map<String, dynamic>> points) {
    return _post('/location/sync', {'points': points}, token: token);
  }

  Future<void> sendEvent(String token, String eventType,
      {int? battery, double? lat, double? lng, Map<String, dynamic>? metadata}) {
    return _post('/device/event', {
      'event_type': eventType,
      if (battery != null) 'battery': battery,
      if (lat != null) 'latitude': lat,
      if (lng != null) 'longitude': lng,
      if (metadata != null) 'metadata': metadata,
    }, token: token);
  }
}
