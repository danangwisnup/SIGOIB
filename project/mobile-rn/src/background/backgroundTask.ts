// Background task: foreground service Android via react-native-background-actions (4.0.1).
// Bukan satu-satunya mekanisme: polling juga berjalan di foreground.
// iOS: background fetch/location mengikuti kapabilitas OS (lihat README).
//
// CATATAN NATIVE (WAJIB untuk Android 14 / targetSdk 34) — lihat NATIVE_ANDROID_CHANGES.md:
//   AndroidManifest HARUS mendeklarasikan service RNBackgroundActionsTask dengan
//   android:foregroundServiceType="location" + permission FOREGROUND_SERVICE_LOCATION,
//   POST_NOTIFICATIONS, WAKE_LOCK. Versi 4.0.1 TIDAK menerima foregroundServiceType via JS,
//   jadi deklarasi manifest adalah satu-satunya cara agar start() tidak crash di Android 14.
import BackgroundService from 'react-native-background-actions';
import {trackingTick} from '../tracking/trackingController';

const TASK_OPTIONS = {
  taskName: 'SIGoIB Tracking',
  taskTitle: 'Monitoring IB',
  taskDesc: 'Perangkat siap.',
  taskIcon: {name: 'ic_launcher', type: 'mipmap'},
  color: '#16241a',
  parameters: {},
};

function isRunningSafe(): boolean {
  try {
    return BackgroundService.isRunning();
  } catch {
    return false;
  }
}

async function backgroundTask(): Promise<void> {
  await new Promise<void>(resolve => {
    const loop = async () => {
      while (isRunningSafe()) {
        try {
          await trackingTick();
        } catch {
          // isolasi error per tick: satu tick gagal tidak menghentikan loop
        }
        await new Promise(r => setTimeout(r, 10000)); // tick tiap 10 dtk; cadence diatur controller
      }
      resolve();
    };
    void loop();
  });
}

export async function startBackgroundTracking(): Promise<void> {
  if (isRunningSafe()) {
    return;
  }
  // Dibiarkan melempar bila gagal; pemanggil (trackingController) menangkap & degrade.
  await BackgroundService.start(backgroundTask, TASK_OPTIONS);
}

export async function stopBackgroundTracking(): Promise<void> {
  if (!isRunningSafe()) {
    return;
  }
  try {
    await BackgroundService.stop();
  } catch {
    // abaikan kegagalan stop
  }
}
