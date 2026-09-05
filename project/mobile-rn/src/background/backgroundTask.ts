// Background task: foreground service Android via react-native-background-actions.
// Bukan satu-satunya mekanisme: polling juga berjalan di foreground.
// iOS: background fetch/location mengikuti kapabilitas OS (lihat README).
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

async function backgroundTask(): Promise<void> {
  await new Promise<void>(async () => {
    while (BackgroundService.isRunning()) {
      try {
        await trackingTick();
      } catch {
        // isolasi error per tick
      }
      await new Promise(r => setTimeout(r, 10000)); // tick tiap 10 dtk; cadence diatur controller
    }
  });
}

export async function startBackgroundTracking(): Promise<void> {
  if (BackgroundService.isRunning()) {
    return;
  }
  await BackgroundService.start(backgroundTask, TASK_OPTIONS);
}

export async function stopBackgroundTracking(): Promise<void> {
  if (BackgroundService.isRunning()) {
    await BackgroundService.stop();
  }
}
