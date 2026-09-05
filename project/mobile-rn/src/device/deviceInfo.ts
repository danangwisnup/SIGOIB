// device_uuid STABIL per perangkat fisik:
// Android: ANDROID_ID, iOS: identifierForVendor.
// Stabil setelah uninstall/install ulang -> server mengenali perangkat yang sama
// (fix bug reinstall; perangkat berbeda tetap menghasilkan uuid berbeda).
import {Platform} from 'react-native';
import DeviceInfo from 'react-native-device-info';

let cachedUuid: string | null = null;

export async function getDeviceUuid(): Promise<string> {
  if (cachedUuid) {
    return cachedUuid;
  }
  if (Platform.OS === 'android') {
    cachedUuid = 'and-' + (await DeviceInfo.getAndroidId());
  } else {
    cachedUuid = 'ios-' + (await DeviceInfo.getUniqueId());
  }
  return cachedUuid;
}

export async function getDeviceInfo(): Promise<{platform: string; model: string}> {
  const model = await DeviceInfo.getModel();
  return {platform: Platform.OS, model};
}

export async function getBatteryLevel(): Promise<number | undefined> {
  try {
    const level = await DeviceInfo.getBatteryLevel();
    return Math.round(level * 100);
  } catch {
    return undefined;
  }
}
