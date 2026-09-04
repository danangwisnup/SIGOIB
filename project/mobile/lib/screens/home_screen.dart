// Layar utama: MONITORING AKTIF / STANDBY.
// Tanpa tombol Start/Stop Tracking - semuanya mengikuti server.
import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import '../services/storage_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, String> _ui = {};
  bool _netOk = true;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _refresh();
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => _refresh());
    Connectivity().onConnectivityChanged.listen((results) {
      setState(() => _netOk = results.any((r) => r != ConnectivityResult.none));
    });
  }

  void _refresh() {
    setState(() => _ui = StorageService.instance.readUiState());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Widget _statusIcon(bool ok) =>
      Icon(ok ? Icons.check_circle : Icons.cancel, color: ok ? Colors.green : Colors.red, size: 20);

  @override
  Widget build(BuildContext context) {
    final state = _ui['state'] ?? '';
    final tracking = state == 'TRACKING';
    final revoked = state == 'REVOKED';
    final sessions = _ui['session_names'] ?? '';
    final nrp = StorageService.instance.nrp ?? '';

    return Scaffold(
      appBar: AppBar(title: const Text('MONITORING IB'), centerTitle: true),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (revoked) ...[
              const Icon(Icons.block, size: 64, color: Colors.red),
              const SizedBox(height: 12),
              const Text('PERANGKAT NONAKTIF', textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Perangkat ini sudah tidak aktif. Hubungi admin.',
                  textAlign: TextAlign.center),
            ] else if (tracking) ...[
              const Icon(Icons.circle, size: 64, color: Colors.green),
              const SizedBox(height: 12),
              const Text('AKTIF', textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.green)),
              const SizedBox(height: 8),
              if (sessions.isNotEmpty)
                Text(sessions, textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 15)),
            ] else ...[
              const Icon(Icons.circle_outlined, size: 64, color: Colors.grey),
              const SizedBox(height: 12),
              const Text('STANDBY', textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.grey)),
              const SizedBox(height: 8),
              const Text('Tidak ada monitoring aktif saat ini.\nPerangkat siap.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
            ],
            const SizedBox(height: 32),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    if (nrp.isNotEmpty)
                      ListTile(dense: true, leading: const Icon(Icons.badge),
                          title: Text('NRP $nrp')),
                    ListTile(dense: true,
                      leading: const Icon(Icons.gps_fixed),
                      title: const Text('GPS'),
                      trailing: _statusIcon((_ui['gps_ok'] ?? 'ON') == 'ON')),
                    ListTile(dense: true,
                      leading: const Icon(Icons.wifi),
                      title: const Text('Internet'),
                      trailing: _statusIcon(_netOk)),
                    ListTile(dense: true,
                      leading: const Icon(Icons.battery_std),
                      title: const Text('Battery'),
                      trailing: Text((_ui['battery'] ?? '-') + ((_ui['battery'] ?? '').isEmpty ? '' : '%'))),
                    ListTile(dense: true,
                      leading: const Icon(Icons.access_time),
                      title: const Text('Update terakhir'),
                      trailing: Text((_ui['last_update'] ?? '-').length >= 8
                          ? (_ui['last_update'] ?? '').substring(11)
                          : '-')),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
