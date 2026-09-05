// Kirim device event penting (tidak spam).
import {api} from '../api/client';
import {DeviceEventType} from '../types';
import {getDeviceToken} from '../storage/secure';
import {getBatteryLevel} from '../device/deviceInfo';

export async function sendDeviceEvent(type: DeviceEventType): Promise<void> {
  try {
    const token = await getDeviceToken();
    if (!token) {
      return;
    }
    await api.sendEvent(token, type, {battery: await getBatteryLevel()});
  } catch {
    // event bersifat best-effort
  }
}
