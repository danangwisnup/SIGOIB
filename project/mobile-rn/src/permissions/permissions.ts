// Permission satu kali (setup screen). Tidak meminta ulang yang sudah granted.
import {Platform} from 'react-native';
import {
  check,
  request,
  PERMISSIONS,
  RESULTS,
  Permission,
} from 'react-native-permissions';
import Geolocation from 'react-native-geolocation-service';

export interface PermissionChecklist {
  location: boolean;
  backgroundLocation: boolean;
  notification: boolean;
  gps: boolean;
}

const LOC: Permission =
  Platform.OS === 'ios'
    ? PERMISSIONS.IOS.LOCATION_WHEN_IN_USE
    : PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION;

const BG_LOC: Permission | null =
  Platform.OS === 'ios'
    ? PERMISSIONS.IOS.LOCATION_ALWAYS
    : PERMISSIONS.ANDROID.ACCESS_BACKGROUND_LOCATION;

const NOTIF: Permission | null =
  Platform.OS === 'android' && (PERMISSIONS.ANDROID as Record<string, unknown>).POST_NOTIFICATIONS
    ? ((PERMISSIONS.ANDROID as Record<string, Permission>).POST_NOTIFICATIONS as Permission)
    : null;

export async function checkPermissions(): Promise<PermissionChecklist> {
  const gps = await GeolocationIsEnabled();
  return {
    location: (await check(LOC)) === RESULTS.GRANTED,
    backgroundLocation: BG_LOC ? (await check(BG_LOC)) === RESULTS.GRANTED : true,
    notification: NOTIF ? (await check(NOTIF)) === RESULTS.GRANTED : true,
    gps,
  };
}

export async function requestAllPermissions(): Promise<PermissionChecklist> {
  await request(LOC);
  if (BG_LOC) {
    await request(BG_LOC);
  }
  if (NOTIF) {
    await request(NOTIF);
  }
  return checkPermissions();
}

async function GeolocationIsEnabled(): Promise<boolean> {
  try {
    if (Platform.OS === 'android') {
      return await DeviceHasGps();
    }
    return true; // iOS: permission location mencakup layanan
  } catch {
    return false;
  }
}

async function DeviceHasGps(): Promise<boolean> {
  return new Promise(resolve => {
    Geolocation.getCurrentPosition(
      () => resolve(true),
      err => resolve(err.code !== 1), // 1 = PERMISSION_DENIED
      {enableHighAccuracy: false, timeout: 8000, maximumAge: 60000},
    );
  });
}
