// PENGATURAN PERANGKAT (one-time setup): permission + test perangkat.
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart' as ph;
import '../services/api_service.dart';
import '../services/storage_service.dart';
import 'home_screen.dart';

class SetupScreen extends StatefulWidget {
  const SetupScreen({super.key});
  @override
  State<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends State<SetupScreen> {
  bool _location = false;
  bool _backgroundLocation = false;
  bool _notification = false;
  bool _batteryOpt = false;
  final Map<String, bool?> _test = {'GPS': null, 'Internet': null, 'Server': null, 'Permission': null};
  bool _testing = false;
  bool _done = false;

  @override
  void initState() {
    super.initState();
    _refreshPermissions();
  }

  Future<void> _refreshPermissions() async {
    final loc = await ph.Permission.location.isGranted;
    final bg = await ph.Permission.locationAlways.isGranted;
    final notif = Platform.isAndroid ? await ph.Permission.notification.isGranted : true;
    final batt = Platform.isAndroid ? await ph.Permission.ignoreBatteryOptimizations.isGranted : true;
    setState(() {
      _location = loc;
      _backgroundLocation = bg;
      _notification = notif;
      _batteryOpt = batt;
    });
  }

  Future<void> _requestPermissions() async {
    await ph.Permission.location.request();
    await ph.Permission.locationAlways.request();
    if (Platform.isAndroid) {
      await ph.Permission.notification.request();
      await ph.Permission.ignoreBatteryOptimizations.request();
    }
    await _refreshPermissions();
  }

  Future<void> _runTest() async {
    setState(() { _testing = true; _done = false; });
    // 1. Permission
    final permOk = _location;
    // 2. GPS
    bool gpsOk = false;
    try {
      gpsOk = await Geolocator.isLocationServiceEnabled();
      if (gpsOk && permOk) {
        await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium))
            .timeout(const Duration(seconds: 15));
      }
    } catch (_) { gpsOk = false; }
    // 3. Internet + 4. Server
    bool netOk = false;
    bool serverOk = false;
    try {
      final token = await StorageService.instance.deviceToken;
      if (token != null) {
        await ApiService.instance.statusByToken(token);
        netOk = true;
        serverOk = true;
      }
    } on ApiException {
      netOk = true; // server merespons, tapi token bermasalah
    } catch (_) {}

    setState(() {
      _test['Permission'] = permOk;
      _test['GPS'] = gpsOk;
      _test['Internet'] = netOk;
      _test['Server'] = serverOk;
      _testing = false;
      _done = permOk && gpsOk && netOk && serverOk;
    });
  }

  Widget _row(String label, bool ok, {VoidCallback? onTap}) {
    return ListTile(
      dense: true,
      title: Text(label),
      trailing: Icon(ok ? Icons.check_circle : Icons.error_outline,
          color: ok ? Colors.green : Colors.orange),
      onTap: onTap,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('PENGATURAN PERANGKAT')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _row('Lokasi', _location),
          _row('Background Location', _backgroundLocation),
          _row('Notifikasi', _notification),
          _row('Pengaturan Battery', _batteryOpt),
          const SizedBox(height: 8),
          OutlinedButton(
            key: const Key('request-permission-btn'),
            onPressed: _requestPermissions,
            child: const Text('Izinkan Semua'),
          ),
          const SizedBox(height: 24),
          FilledButton.icon(
            key: const Key('test-device-btn'),
            onPressed: _testing ? null : _runTest,
            icon: const Icon(Icons.play_arrow),
            label: Text(_testing ? 'MENGUJI...' : 'TEST PERANGKAT'),
          ),
          const SizedBox(height: 16),
          ..._test.entries.map((e) => e.value == null
              ? const SizedBox.shrink()
              : _row(e.key, e.value!)),
          if (_done) ...[
            const SizedBox(height: 24),
            const Text('PERANGKAT SIAP',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.green)),
            const SizedBox(height: 12),
            FilledButton(
              key: const Key('go-home-btn'),
              onPressed: () => Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const HomeScreen())),
              child: const Text('MASUK'),
            ),
          ],
        ],
      ),
    );
  }
}
