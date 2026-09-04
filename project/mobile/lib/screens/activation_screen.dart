// AKTIVASI PERANGKAT: masukkan NRP -> server cek -> PENDING -> poll status
// -> APPROVED (terima device token) -> setup. Tanpa OTP/SMS/password.
import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/background_service.dart';
import '../services/storage_service.dart';
import '../services/tracking_controller.dart';
import 'setup_screen.dart';

class ActivationScreen extends StatefulWidget {
  const ActivationScreen({super.key});
  @override
  State<ActivationScreen> createState() => _ActivationScreenState();
}

class _ActivationScreenState extends State<ActivationScreen> {
  final _nrpController = TextEditingController();
  String _phase = 'input'; // input | pending | error
  String _message = '';
  Timer? _pollTimer;

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _submit() async {
    final nrp = _nrpController.text.trim();
    if (nrp.isEmpty) return;
    setState(() { _phase = 'loading'; _message = ''; });
    try {
      final storage = StorageService.instance;
      final info = await TrackingController.deviceInfo();
      final status = await ApiService.instance.register(
        nrp: nrp,
        deviceUuid: storage.deviceUuid,
        platform: info['platform']!,
        model: info['model']!,
      );
      storage.nrp = nrp;
      _handleStatus(status.deviceStatus, status.message);
      if (status.deviceStatus == 'PENDING') _startPolling();
    } on ApiException catch (e) {
      setState(() { _phase = 'error'; _message = e.message; });
    } catch (_) {
      setState(() { _phase = 'error'; _message = 'Tidak dapat terhubung ke server.'; });
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) => _checkStatus());
  }

  Future<void> _checkStatus() async {
    try {
      final status = await ApiService.instance.statusByUuid(StorageService.instance.deviceUuid);
      _handleStatus(status.deviceStatus, status.message, token: status.deviceToken);
    } catch (_) {}
  }

  Future<void> _handleStatus(String deviceStatus, String? message, {String? token}) async {
    switch (deviceStatus) {
      case 'PENDING':
        setState(() { _phase = 'pending'; _message = message ?? 'Menunggu persetujuan admin.'; });
        break;
      case 'ACTIVE':
        _pollTimer?.cancel();
        if (token != null) {
          await StorageService.instance.setDeviceToken(token);
          await initBackgroundService();
        }
        if (mounted) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (_) => const SetupScreen()),
          );
        }
        break;
      case 'REVOKED':
        setState(() {
          _phase = 'error';
          _message = message ?? 'Perangkat ini sudah tidak aktif. Hubungi admin.';
        });
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 48),
              const Text('AKTIVASI PERANGKAT',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              const Text('Masukkan NRP Anda untuk mendaftarkan perangkat ini.',
                  textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
              const SizedBox(height: 32),
              if (_phase == 'input' || _phase == 'error' || _phase == 'loading') ...[
                TextField(
                  key: const Key('nrp-input'),
                  controller: _nrpController,
                  decoration: const InputDecoration(
                    labelText: 'NRP', border: OutlineInputBorder()),
                  keyboardType: TextInputType.number,
                  enabled: _phase != 'loading',
                ),
                const SizedBox(height: 16),
                FilledButton(
                  key: const Key('lanjut-btn'),
                  onPressed: _phase == 'loading' ? null : _submit,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    child: _phase == 'loading'
                        ? const SizedBox(height: 20, width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('LANJUT'),
                  ),
                ),
              ],
              if (_phase == 'pending') ...[
                const Icon(Icons.hourglass_top, size: 56, color: Colors.orange),
                const SizedBox(height: 16),
                Text(_message, textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 16)),
                const SizedBox(height: 8),
                const Text('Halaman ini akan otomatis lanjut setelah admin menyetujui.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.black54, fontSize: 12)),
              ],
              if (_message.isNotEmpty && _phase == 'error')
                Padding(
                  padding: const EdgeInsets.only(top: 16),
                  child: Text(_message, textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.red)),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
