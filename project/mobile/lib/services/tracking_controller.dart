// Inti logika tracking. Server = source of truth.
// - STANDBY: GPS OFF, polling ringan (default 60 dtk).
// - TRACKING: GPS tiap interval server (default 30 dtk), buffer -> batch sync.
// - Offline: point masuk SQLite queue, sync saat internet kembali.
// - Tidak ada tombol start/stop: semua mengikuti status server.
import 'dart:async';
import 'dart:io';
import 'package:battery_plus/battery_plus.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:geolocator/geolocator.dart';
import 'package:uuid/uuid.dart';

import 'api_service.dart';
import 'offline_queue.dart';
import 'storage_service.dart';

class TrackingController {
  TrackingController._();
  static final TrackingController instance = TrackingController._();

  final _battery = Battery();
  bool _running = false;
  bool _tracking = false;
  DateTime _lastPoll = DateTime.fromMillisecondsSinceEpoch(0);
  DateTime _lastGps = DateTime.fromMillisecondsSinceEpoch(0);
  int _pollInterval = 60;
  int _gpsInterval = 30;
  bool _wasTracking = false;
  bool _netOnline = true;

  static const int batchSize = 5;

  Future<void> start() async {
    if (_running) return;
    _running = true;
    Connectivity().onConnectivityChanged.listen((results) {
      final online = results.any((r) => r != ConnectivityResult.none);
      if (online != _netOnline) {
        _netOnline = online;
        _sendEventSafe(online ? 'NETWORK_ONLINE' : 'NETWORK_OFFLINE');
        if (online) _syncQueue();
      }
    });
    Timer.periodic(const Duration(seconds: 10), (_) => tick());
    await _sendEventSafe('APP_STARTED');
    await tick();
  }

  Future<void> tick() async {
    final storage = StorageService.instance;
    final token = await storage.deviceToken;
    if (token == null) return; // belum diapprove / belum aktivasi

    final now = DateTime.now();
    if (now.difference(_lastPoll).inSeconds < _pollInterval && !_tracking) {
      return;
    }
    if (_tracking && now.difference(_lastGps).inSeconds < _gpsInterval) {
      return;
    }

    int? batt;
    try { batt = await _battery.batteryLevel; } catch (_) {}

    // --- Poll status server ---
    try {
      final status = await ApiService.instance.statusByToken(token, battery: batt);
      _lastPoll = now;
      _pollInterval = status.standbyPollInterval;
      _gpsInterval = status.trackingInterval;

      if (status.trackingRequired && !_wasTracking) {
        _wasTracking = true;
        _sendEventSafe('TRACKING_STARTED');
      } else if (!status.trackingRequired && _wasTracking) {
        _wasTracking = false;
        _sendEventSafe('TRACKING_STOPPED');
      }
      _tracking = status.trackingRequired;

      await storage.writeUiState({
        'state': _tracking ? 'TRACKING' : 'STANDBY',
        'session_names': status.activeSessions.map((s) => s.name).join(', '),
        'server_time': status.serverTime,
        'battery': '${batt ?? ''}',
      });
    } on ApiException catch (e) {
      if (e.statusCode == 401 || e.statusCode == 403) {
        // Device revoked / token tidak valid: berhenti mengirim GPS.
        _tracking = false;
        await storage.writeUiState({'state': 'REVOKED'});
        return;
      }
      _tracking = _wasTracking; // server error: pertahankan state terakhir
    } catch (_) {
      // Tidak ada internet: GPS tetap jalan jika tracking, data masuk queue.
      await StorageService.instance.writeUiState({'net_ok': 'OFF'});
    }

    // --- Ambil GPS saat tracking ---
    if (_tracking && now.difference(_lastGps).inSeconds >= _gpsInterval) {
      await _capturePoint(batt);
      _lastGps = now;
    }

    // --- Batch sync ---
    if (_netOnline) {
      await _syncQueue();
    }
  }

  Future<void> _capturePoint(int? batt) async {
    try {
      final serviceOn = await Geolocator.isLocationServiceEnabled();
      if (!serviceOn) {
        _sendEventSafe('GPS_DISABLED', battery: batt);
        await StorageService.instance.writeUiState({'gps_ok': 'OFF'});
        return;
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      await OfflineQueue.instance.enqueue({
        'client_point_id': const Uuid().v4(),
        'latitude': pos.latitude,
        'longitude': pos.longitude,
        'accuracy': pos.accuracy,
        'altitude': pos.altitude,
        'speed': pos.speed,
        'battery': batt,
        'recorded_at': _fmt(pos.timestamp),
      });
      await StorageService.instance.writeUiState({
        'gps_ok': 'ON',
        'last_update': _fmt(DateTime.now()),
      });
    } catch (e) {
      await StorageService.instance.writeUiState({'gps_ok': 'OFF'});
    }
  }

  Future<void> _syncQueue() async {
    final token = await StorageService.instance.deviceToken;
    if (token == null) return;
    final pending = await OfflineQueue.instance.pending(limit: 50);
    if (pending.isEmpty) return;

    // Kirim batch 5-10 point per request
    final chunks = <List<Map<String, dynamic>>>[];
    for (var i = 0; i < pending.length; i += batchSize) {
      chunks.add(pending.sublist(i, i + batchSize > pending.length ? pending.length : i + batchSize));
    }
    for (final chunk in chunks) {
      try {
        await ApiService.instance.syncLocations(token, chunk.map((p) => {
          'client_point_id': p['client_point_id'],
          'latitude': p['latitude'],
          'longitude': p['longitude'],
          'accuracy': p['accuracy'],
          'altitude': p['altitude'],
          'speed': p['speed'],
          'battery': p['battery'],
          'recorded_at': p['recorded_at'],
        }).toList());
        await OfflineQueue.instance.removeIds(chunk.map((p) => p['id'] as int).toList());
        await StorageService.instance.writeUiState({'net_ok': 'ON'});
      } catch (_) {
        break; // internet mati lagi / server error: coba di tick berikutnya
      }
    }
  }

  Future<void> _sendEventSafe(String type, {int? battery}) async {
    try {
      final token = await StorageService.instance.deviceToken;
      if (token == null) return;
      await ApiService.instance.sendEvent(token, type, battery: battery);
    } catch (_) {}
  }

  static Future<Map<String, String>> deviceInfo() async {
    final info = DeviceInfoPlugin();
    if (Platform.isAndroid) {
      final a = await info.androidInfo;
      return {'platform': 'android', 'model': '${a.manufacturer} ${a.model}'};
    }
    if (Platform.isIOS) {
      final i = await info.iosInfo;
      return {'platform': 'ios', 'model': i.utsname.machine};
    }
    return {'platform': 'unknown', 'model': 'unknown'};
  }

  static String _fmt(DateTime t) =>
      '${t.year.toString().padLeft(4, '0')}-${t.month.toString().padLeft(2, '0')}-${t.day.toString().padLeft(2, '0')} '
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}:${t.second.toString().padLeft(2, '0')}';
}
