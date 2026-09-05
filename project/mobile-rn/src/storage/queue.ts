// Offline queue SQLite. Hapus hanya setelah server konfirmasi berhasil.
// client_point_id = idempotency key (server INSERT IGNORE).
// Semua operasi dibungkus: kegagalan membuka DB TIDAK boleh meng-crash aplikasi.
import SQLite from 'react-native-sqlite-storage';
import {LocationPoint} from '../types';

SQLite.enablePromise(true);

// Tipe minimal DB (library tidak menyediakan tipe resmi)
interface SqlDb {
  executeSql(sql: string, params?: unknown[]): Promise<[any]>;
}

let db: SqlDb | null = null;
let openFailed = false;

async function getDb(): Promise<SqlDb | null> {
  if (db) {
    return db;
  }
  if (openFailed) {
    return null;
  }
  try {
    const opened: SqlDb = await SQLite.openDatabase({
      name: 'sigoib_queue.db',
      location: 'default',
    });
    await opened.executeSql(`CREATE TABLE IF NOT EXISTS points(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      client_point_id TEXT UNIQUE,
      latitude REAL NOT NULL,
      longitude REAL NOT NULL,
      accuracy REAL,
      altitude REAL,
      speed REAL,
      battery INTEGER,
      recorded_at TEXT NOT NULL
    )`);
    db = opened;
    return db;
  } catch {
    // SQLite gagal dibuka -> jangan crash. Queue offline nonaktif sementara,
    // tracking/polling tetap berjalan.
    openFailed = true;
    return null;
  }
}

export interface QueuedPoint extends LocationPoint {
  id: number;
}

export async function enqueue(point: LocationPoint): Promise<void> {
  const d = await getDb();
  if (!d) {
    return;
  }
  try {
    await d.executeSql(
      'INSERT OR IGNORE INTO points (client_point_id, latitude, longitude, accuracy, altitude, speed, battery, recorded_at) VALUES (?,?,?,?,?,?,?,?)',
      [
        point.client_point_id,
        point.latitude,
        point.longitude,
        point.accuracy,
        point.altitude,
        point.speed,
        point.battery,
        point.recorded_at,
      ],
    );
  } catch {
    // abaikan kegagalan tulis tunggal
  }
}

export async function pending(limit = 50): Promise<QueuedPoint[]> {
  const d = await getDb();
  if (!d) {
    return [];
  }
  try {
    const [rs] = await d.executeSql('SELECT * FROM points ORDER BY id ASC LIMIT ?', [limit]);
    const rows: QueuedPoint[] = [];
    for (let i = 0; i < rs.rows.length; i++) {
      rows.push(rs.rows.item(i));
    }
    return rows;
  } catch {
    return [];
  }
}

export async function removeIds(ids: number[]): Promise<void> {
  if (!ids.length) {
    return;
  }
  const d = await getDb();
  if (!d) {
    return;
  }
  try {
    await d.executeSql(
      `DELETE FROM points WHERE id IN (${ids.map(() => '?').join(',')})`,
      ids,
    );
  } catch {
    // abaikan
  }
}

export async function queueCount(): Promise<number> {
  const d = await getDb();
  if (!d) {
    return 0;
  }
  try {
    const [rs] = await d.executeSql('SELECT COUNT(*) AS c FROM points');
    return rs.rows.item(0).c as number;
  } catch {
    return 0;
  }
}
