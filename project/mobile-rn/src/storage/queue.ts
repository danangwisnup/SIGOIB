// Offline queue SQLite. Hapus hanya setelah server konfirmasi berhasil.
// client_point_id = idempotency key (server INSERT IGNORE).
import SQLite from 'react-native-sqlite-storage';
import {LocationPoint} from '../types';

SQLite.enablePromise(true);

// Tipe minimal DB (library tidak menyediakan tipe resmi)
interface SqlDb {
  executeSql(sql: string, params?: unknown[]): Promise<[any]>;
}

let db: SqlDb | null = null;

async function getDb(): Promise<SqlDb> {
  if (db) {
    return db;
  }
  const opened: SqlDb = await SQLite.openDatabase({
    name: 'sigoib_queue.db',
    location: 'default',
  });
  db = opened;
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
  return opened;
}

export interface QueuedPoint extends LocationPoint {
  id: number;
}

export async function enqueue(point: LocationPoint): Promise<void> {
  const d = await getDb();
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
}

export async function pending(limit = 50): Promise<QueuedPoint[]> {
  const d = await getDb();
  const [rs] = await d.executeSql('SELECT * FROM points ORDER BY id ASC LIMIT ?', [limit]);
  const rows: QueuedPoint[] = [];
  for (let i = 0; i < rs.rows.length; i++) {
    rows.push(rs.rows.item(i));
  }
  return rows;
}

export async function removeIds(ids: number[]): Promise<void> {
  if (!ids.length) {
    return;
  }
  const d = await getDb();
  await d.executeSql(
    `DELETE FROM points WHERE id IN (${ids.map(() => '?').join(',')})`,
    ids,
  );
}

export async function queueCount(): Promise<number> {
  const d = await getDb();
  const [rs] = await d.executeSql('SELECT COUNT(*) AS c FROM points');
  return rs.rows.item(0).c as number;
}
