// Inti logika tracking. Server = source of truth.
// STANDBY: GPS OFF, polling ringan (~60 dtk, dari server).
// TRACKING: GPS tiap interval server (~30 dtk), buffer -> batch sync 5-10 titik.
// Offline: titik masuk SQLite queue; sync saat internet kembali.
// Revoked (401/403): hentikan tracking + hapus token lokal.
import NetInfo from '@react-native-community/netinfo';
import Geolocation from 'react-native-geolocation-service';
import BackgroundService from 'react-native-background-actions';
import {api} from '../api/client';
import {ApiError, LocationPoint} from '../types';
import {getDeviceToken, clearDeviceToken} from '../storage/secure';
import {enqueue, pending, removeIds, queueCount} from '../storage/queue';
import {getBatteryLevel} from '../device/deviceInfo';
import {sendDeviceEvent} from '../services/events';
import {setUiState} from '../services/uiState';
import {formatServerTime, newClientPointId} from '../utils/time';

const BATCH_SIZE = 8;

let running = false;
let tracking = false;
let pollIntervalSec = 60;
let gpsIntervalSec = 30;
let lastPoll = 0;
let lastGps = 0;
let netOnline = true;
let lastBatteryEventSent = 0;

function nowSec(): number {
  return Math.floor(Date.now() / 1000);
}

export function initTracking(): void {
  if (running) {
    return;
  }
  running = true;

  NetInfo.addEventListener(state => {
    const online = !!state.isConnected;
    if (online !== netOnline) {
      netOnline = online;
      setUiState({netOk: online});
      sendDeviceEvent(online ? 'NETWORK_ONLINE' : 'NETWORK_OFFLINE');
      if (online) {
        void syncQueue();
      }
    }
  });

  sendDeviceEvent('APP_STARTED');
  // Foreground tick: dilewati jika background service sedang berjalan (hindari GPS ganda).
  setInterval(() => {
    if (!BackgroundService.isRunning()) {
      void trackingTick();
    }
  }, 10000);
  void trackingTick();
}

export async function trackingTick(): Promise<void> {
  const token = await getDeviceToken();
  if (!token) {
    return; // belum aktivasi / belum diapprove
  }
  const now = nowSec();
  if (!tracking && now - lastPoll < pollIntervalSec) {
    return;
  }
  if (tracking && now - lastGps < gpsIntervalSec) {
    return;
  }

  const battery = await getBatteryLevel();

  try {
    const status = await api.statusByToken(token, battery);
    lastPoll = now;
    pollIntervalSec = status.standby_poll_interval || 60;
    gpsIntervalSec = status.tracking_interval || 30;

    if (status.tracking_required && !tracking) {
      tracking = true;
      await sendDeviceEvent('TRACKING_STARTED');
    } else if (!status.tracking_required && tracking) {
      tracking = false;
      await sendDeviceEvent('TRACKING_STOPPED');
    }
    tracking = status.tracking_required;

    setUiState({
      state: tracking ? 'TRACKING' : 'STANDBY',
      personnelName: status.personnel?.name ?? '',
      nrp: status.personnel?.nrp ?? '',
      rank: status.personnel?.rank ?? '',
      sessions: status.active_sessions.map(s => s.name),
      battery: battery ?? null,
      serverTime: status.server_time,
      netOk: true,
    });

    // Battery event (threshold crossing saja, throttle 30 mnt)
    if (battery !== undefined && battery <= 15 && now - lastBatteryEventSent > 1800) {
      lastBatteryEventSent = now;
      await sendDeviceEvent('BATTERY_LOW');
    }
  } catch (e) {
    const err = e as ApiError;
    if (err.status === 401 || err.status === 403) {
      // Token dicabut / device revoked -> stop total.
      tracking = false;
      await clearDeviceToken();
      setUiState({state: 'REVOKED'});
      return;
    }
    setUiState({netOk: false}); // offline: GPS tetap jalan jika tracking
  }

  if (tracking && now - lastGps >= gpsIntervalSec) {
    await capturePoint(battery);
    lastGps = now;
  }
  if (netOnline) {
    await syncQueue();
  }
}

function capturePoint(battery?: number): Promise<void> {
  return new Promise(resolve => {
    Geolocation.getCurrentPosition(
      async pos => {
        const point: LocationPoint = {
          client_point_id: newClientPointId(),
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy: pos.coords.accuracy ?? null,
          altitude: pos.coords.altitude ?? null,
          speed: pos.coords.speed ?? null,
          battery: battery ?? null,
          recorded_at: formatServerTime(new Date(pos.timestamp)),
        };
        await enqueue(point);
        setUiState({
          gpsOk: true,
          lastSync: formatServerTime(new Date()),
          queueCount: await queueCount(),
        });
        resolve();
      },
      async err => {
        setUiState({gpsOk: false});
        if (err.code === 2) {
          // 2 = POSITION_UNAVAILABLE
          await sendDeviceEvent('GPS_DISABLED');
        }
        resolve();
      },
      {enableHighAccuracy: true, timeout: 15000, maximumAge: 5000},
    );
  });
}

export async function syncQueue(): Promise<void> {
  const token = await getDeviceToken();
  if (!token) {
    return;
  }
  const rows = await pending(50);
  if (!rows.length) {
    setUiState({queueCount: 0});
    return;
  }
  for (let i = 0; i < rows.length; i += BATCH_SIZE) {
    const chunk = rows.slice(i, i + BATCH_SIZE);
    try {
      await api.syncLocations(
        token,
        chunk.map(({id, ...p}) => p),
      );
      // Hapus lokal HANYA setelah server konfirmasi berhasil.
      await removeIds(chunk.map(c => c.id));
    } catch {
      break; // gagal -> titik tetap di queue, coba tick berikutnya
    }
  }
  setUiState({queueCount: await queueCount()});
}
