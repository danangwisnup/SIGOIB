// Penyimpanan lokal: device_token di secure storage, sisanya di SharedPreferences.
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

class StorageService {
  StorageService._();
  static final StorageService instance = StorageService._();

  static const _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  late SharedPreferences _prefs;

  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  // device_uuid: dibuat sekali saat install.
  String get deviceUuid {
    var uuid = _prefs.getString('device_uuid');
    if (uuid == null) {
      uuid = const Uuid().v4();
      _prefs.setString('device_uuid', uuid);
    }
    return uuid;
  }

  String? get nrp => _prefs.getString('nrp');
  set nrp(String? v) => v == null ? _prefs.remove('nrp') : _prefs.setString('nrp', v);

  // Device token = kredensial sensitif -> secure storage.
  Future<String?> get deviceToken => _secure.read(key: 'device_token');
  Future<void> setDeviceToken(String? v) async =>
      v == null ? _secure.delete(key: 'device_token') : _secure.write(key: 'device_token', value: v);

  // Status terakhir untuk ditampilkan UI (ditulis oleh background service).
  Map<String, String> readUiState() {
    final keys = ['state', 'session_names', 'gps_ok', 'net_ok', 'battery', 'last_update', 'server_time'];
    return {for (final k in keys) k: _prefs.getString('ui_$k') ?? ''};
  }

  Future<void> writeUiState(Map<String, String> state) async {
    for (final e in state.entries) {
      await _prefs.setString('ui_${e.key}', e.value);
    }
  }
}
