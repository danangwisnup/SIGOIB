// Tipe response API existing (backend PHP REST API).

export interface ActiveSession {
  id: number;
  name: string;
  type: 'IB' | 'QUICK_CHECK';
  start_at: string;
  end_at: string;
}

export interface DeviceStatusPayload {
  device_status: 'PENDING' | 'ACTIVE' | 'REVOKED';
  tracking_required: boolean;
  tracking_interval: number; // detik
  standby_poll_interval: number; // detik
  server_time: string;
  personnel?: {
    id: number;
    nrp: string;
    name: string;
    rank: string | null;
  };
  active_sessions: ActiveSession[];
  device_token?: string;
  message?: string;
}

export interface RegisterResponse {
  device_status: 'PENDING' | 'ACTIVE' | 'REVOKED';
  device_token?: string;
  message?: string;
}

export interface LocationPoint {
  client_point_id: string;
  latitude: number;
  longitude: number;
  accuracy: number | null;
  altitude: number | null;
  speed: number | null;
  battery: number | null;
  recorded_at: string; // Y-m-d H:i:s (waktu perangkat)
}

export interface SyncResult {
  received: number;
  inserted: number;
  duplicated: number;
  failed: number;
  tracking_required: boolean;
}

export type DeviceEventType =
  | 'APP_STARTED'
  | 'TRACKING_STARTED'
  | 'TRACKING_STOPPED'
  | 'GPS_DISABLED'
  | 'LOCATION_PERMISSION_CHANGED'
  | 'BATTERY_LOW'
  | 'NETWORK_OFFLINE'
  | 'NETWORK_ONLINE'
  | 'DEVICE_REVOKED';

export interface ApiError {
  status: number;
  message: string;
}
