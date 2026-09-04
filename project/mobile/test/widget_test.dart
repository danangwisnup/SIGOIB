// Smoke test parsing model. Test widget penuh memerlukan plugin native
// (secure storage, dsb) sehingga dijalankan di perangkat/emulator, bukan di sini.
import 'package:flutter_test/flutter_test.dart';
import 'package:monitoring_ib/models/device_status.dart';

void main() {
  test('DeviceStatus parsing payload server', () {
    final s = DeviceStatus.fromJson({
      'device_status': 'ACTIVE',
      'tracking_required': true,
      'tracking_interval': 30,
      'standby_poll_interval': 60,
      'server_time': '2026-06-01 10:00:00',
      'personnel': {'id': 1, 'nrp': '320001', 'name': 'Budi Santoso', 'rank': 'Serka'},
      'active_sessions': [
        {'id': 7, 'name': 'IB Akhir Pekan', 'type': 'IB',
         'start_at': '2026-06-01 08:00:00', 'end_at': '2026-06-02 22:00:00'}
      ],
    });
    expect(s.deviceStatus, 'ACTIVE');
    expect(s.trackingRequired, true);
    expect(s.nrp, '320001');
    expect(s.activeSessions.length, 1);
    expect(s.activeSessions.first.type, 'IB');
  });

  test('DeviceStatus standby default', () {
    final s = DeviceStatus.fromJson({'device_status': 'PENDING', 'message': 'Menunggu persetujuan admin.'});
    expect(s.trackingRequired, false);
    expect(s.message, isNotEmpty);
  });
}
