// Registrasi & status device (NRP -> PENDING -> approve -> ACTIVE).
import {api} from '../api/client';
import {DeviceStatusPayload, RegisterResponse} from '../types';
import {getDeviceInfo, getDeviceUuid} from './deviceInfo';
import {APP_VERSION} from '../config';

export async function registerWithNrp(nrp: string): Promise<RegisterResponse> {
  const deviceUuid = await getDeviceUuid();
  const info = await getDeviceInfo();
  return api.registerDevice({
    nrp,
    deviceUuid,
    platform: info.platform,
    model: info.model,
    appVersion: APP_VERSION,
  });
}

export async function pollStatusByUuid(): Promise<DeviceStatusPayload> {
  return api.statusByUuid(await getDeviceUuid());
}
