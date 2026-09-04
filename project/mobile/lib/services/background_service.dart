// Background service Android/iOS (flutter_background_service).
// Menjalankan TrackingController di background isolate.
import 'dart:ui';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_background_service_android/flutter_background_service_android.dart';

import 'storage_service.dart';
import 'tracking_controller.dart';

Future<void> initBackgroundService() async {
  final service = FlutterBackgroundService();
  await service.configure(
    androidConfiguration: AndroidConfiguration(
      onStart: _onStart,
      autoStart: true,
      isForegroundMode: true,
      notificationChannelId: 'monitoring_ib_channel',
      initialNotificationTitle: 'Monitoring IB',
      initialNotificationContent: 'Perangkat siap.',
      foregroundServiceNotificationId: 8801,
    ),
    iosConfiguration: IosConfiguration(
      autoStart: true,
      onForeground: _onStart,
      onBackground: _onIosBackground,
    ),
  );
  await service.startService();
}

@pragma('vm:entry-point')
void _onStart(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();
  await StorageService.instance.init();
  await TrackingController.instance.start();

  if (service is AndroidServiceInstance) {
    service.on('setAsForeground').listen((_) => service.setAsForegroundService());
  }
  service.on('stopService').listen((_) => service.stopSelf());
}

@pragma('vm:entry-point')
Future<bool> _onIosBackground(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();
  await StorageService.instance.init();
  await TrackingController.instance.tick();
  return true;
}
