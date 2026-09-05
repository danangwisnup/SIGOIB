// HTTP client ke API existing (PHP REST API). Tanpa dummy endpoint.
import {API_BASE_URL} from '../config';
import {
  ApiError,
  DeviceEventType,
  DeviceStatusPayload,
  LocationPoint,
  RegisterResponse,
  SyncResult,
} from '../types';

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  token?: string,
): Promise<T> {
  const headers: Record<string, string> = {'Content-Type': 'application/json'};
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  let res: Response;
  try {
    res = await fetch(`${API_BASE_URL}/api${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw {status: 0, message: 'Tidak dapat terhubung ke server.'} as ApiError;
  }
  let json: {success?: boolean; data?: unknown; message?: string};
  try {
    json = await res.json();
  } catch {
    throw {status: res.status, message: 'Response server tidak valid.'} as ApiError;
  }
  if (!json.success) {
    throw {status: res.status, message: json.message || 'Terjadi kesalahan.'} as ApiError;
  }
  return json.data as T;
}

export interface RegisterParams {
  nrp: string;
  deviceUuid: string; // hardware-stable ID (Android ID / identifierForVendor)
  platform: string;
  model: string;
  appVersion: string;
}

export const api = {
  registerDevice(p: RegisterParams): Promise<RegisterResponse> {
    return request<RegisterResponse>('POST', '/device/register', {
      nrp: p.nrp,
      device_uuid: p.deviceUuid,
      platform: p.platform,
      model: p.model,
      app_version: p.appVersion,
    });
  },

  statusByUuid(deviceUuid: string): Promise<DeviceStatusPayload> {
    return request<DeviceStatusPayload>(
      'GET',
      `/device/status?device_uuid=${encodeURIComponent(deviceUuid)}`,
    );
  },

  statusByToken(token: string, battery?: number): Promise<DeviceStatusPayload> {
    const q = battery !== undefined ? `?battery=${battery}` : '';
    return request<DeviceStatusPayload>('GET', `/device/status${q}`, undefined, token);
  },

  syncLocations(token: string, points: LocationPoint[]): Promise<SyncResult> {
    return request<SyncResult>('POST', '/location/sync', {points}, token);
  },

  sendEvent(
    token: string,
    eventType: DeviceEventType,
    extra?: {battery?: number; latitude?: number; longitude?: number},
  ): Promise<unknown> {
    return request('POST', '/device/event', {event_type: eventType, ...extra}, token);
  },
};
