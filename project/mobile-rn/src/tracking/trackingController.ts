// Inti logika tracking. Server = source of truth.
// STANDBY: GPS OFF, polling ringan (~60 dtk, dari server).
// TRACKING: GPS tiap interval server (~30 dtk), buffer -> batch sync 5-10 titik.
// Offline: titik masuk SQLite queue; sync saat internet kembali.
// Revoked (401/403 atau device_status=REVOKED): hentikan tracking + FGS.
//
// SATU device = SATU GPS stream: `tracking` adalah satu boolean yang ditentukan server dari
// gabungan sesi aktif (IB dan/atau Quick Check). Overlap sesi TIDAK membuat stream kedua.
//
// Foreground Service (FGS) HANYA distart saat tracking_required=true DAN dari foreground.
// Ini mencegah crash Android 14 tepat setelah approval (belum ada sesi aktif) dan saat reopen.
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
import {startBackgroundTracking, stopBackgroundTracking} from '../background/backgroundTask';

const BATCH_SIZE = 8;

let running = false;
let tracking = false;
let pollIntervalSec = 60;
let gpsIntervalSec = 30;
let lastPoll = 0;
let lastGps = 0;
let netOnline = true;
let lastBatteryEventSent = 0;
let fgTimer: ReturnType<typeof setInterval> | null = null;

function nowSec(): number {
  return Math.floor(Date.now() / 1000);
}

// Wrapper aman: modul native bisa gagal/belum siap -> jangan crash.
function bgRunning(): boolean {
  try {
    return BackgroundService.isRunning();
  } catch {
    return false;
  }
}

export function initTracking(): void {
  if (running) {
    return;
  }
  running = true;

  try {
    NetInfo.addEventListener(state => {
      const online = !!state.isConnected;
      if (online !== netOnline) {
        netOnline = online;
        setUiState({netOk: online});
        void sendDeviceEvent(online ? 'NETWORK_ONLINE' : 'NETWORK_OFFLINE');
        if (online) {
          void syncQueue();
        }
      }
    });
  } catch {
    // tanpa NetInfo: anggap online; sync tetap dicoba tiap tick.
  }

  void sendDeviceEvent('APP_STARTED');

  // Foreground tick: dilewati bila FGS berjalan (hindari GPS ganda / satu stream).
  fgTimer = setInterval(() => {
    if (!bgRunning()) {
      void trackingTick();
    }
  }, 10000);
  void trackingTick();
}

export async function trackingTick(): Promise<void> {
  let token: string | null = null;
  try {
    token = await getDeviceToken();
  } catch {
    token = null;
  }
  if (!token) {
    return; // belum aktivasi / token dibersihkan
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

    if (status.device_status === 'REVOKED') {
      await enterRevoked();
      return;
    }

    // Server = source of truth: tracking hanya bila device ACTIVE + tracking_required.
    const shouldTrack = status.device_status === 'ACTIVE' && !!status.tracking_required;
    if (shouldTrack && !tracking) {
      await sendDeviceEvent('TRACKING_STARTED');
    } else if (!shouldTrack && tracking) {
      await sendDeviceEvent('TRACKING_STOPPED');
    }
    tracking = shouldTrack;

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

    // Kelola FGS: satu service, hanya saat perlu.
    await syncBackgroundService();

    // Battery event (threshold crossing saja, throttle 30 mnt)
    if (battery !== undefined && battery <= 15 && now - lastBatteryEventSent > 1800) {
      lastBatteryEventSent = now;
      await sendDeviceEvent('BATTERY_LOW');
    }
  } catch (e) {
    const err = e as ApiError;
    if (err.status === 401 || err.status === 403) {
      // Token dicabut / device revoked -> stop total (token dipertahankan agar RevokedScreen tampil).
      await enterRevoked();
      return;
    }
    setUiState({netOk: false}); // offline: bila tracking, GPS tetap direkam ke queue di bawah.
  }

  if (tracking && now - lastGps >= gpsIntervalSec) {
    await capturePoint(battery);
    lastGps = now;
  }
  if (netOnline) {
    await syncQueue();
  }
}

async function syncBackgroundService(): Promise<void> {
  try {
    if (tracking && !bgRunning()) {
      await startBackgroundTracking();
    } else if (!tracking && bgRunning()) {
      await stopBackgroundTracking();
    }
  } catch {
    // FGS gagal start/stop (batasan OS / permission) -> jangan crash.
    // Foreground tick tetap merekam GPS selama app terbuka.
  }
}

async function enterRevoked(): Promise<void> {
  tracking = false;
  try {
    await stopBackgroundTracking();
  } catch {
    // abaikan
  }
  setUiState({state: 'REVOKED'});
}

// Dipanggil App saat reactivation: hentikan tracking + FGS, lalu token dibersihkan oleh pemanggil.
export async function stopTracking(): Promise<void> {
  tracking = false;
  try {
    await stopBackgroundTracking();
  } catch {
    // abaikan
  }
}

// Bersihkan token secara eksplisit (reactivation).
export async function clearForReactivation(): Promise<void> {
  await stopTracking();
  try {
    await clearDeviceToken();
  } catch {
    // abaikan
  }
}

function capturePoint(battery?: number): Promise<void> {
  return new Promise(resolve => {
    try {
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
          if (err && err.code === 2) {
            // 2 = POSITION_UNAVAILABLE
            await sendDeviceEvent('GPS_DISABLED');
          }
          resolve();
        },
        {enableHighAccuracy: true, timeout: 15000, maximumAge: 5000},
      );
    } catch {
      // Modul Geolocation belum siap -> jangan crash.
      setUiState({gpsOk: false});
      resolve();
    }
  });
}

export async function syncQueue(): Promise<void> {
  let token: string | null = null;
  try {
    token = await getDeviceToken();
  } catch {
    token = null;
  }
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
