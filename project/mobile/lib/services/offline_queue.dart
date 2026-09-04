// Antrian offline SQLite. Setiap point punya client_point_id (idempotency).
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart' as p;

class OfflineQueue {
  OfflineQueue._();
  static final OfflineQueue instance = OfflineQueue._();

  Database? _db;

  Future<Database> get db async {
    if (_db != null) return _db!;
    final path = p.join(await getDatabasesPath(), 'monitoring_queue.db');
    _db = await openDatabase(path, version: 1, onCreate: (d, v) async {
      await d.execute('''
        CREATE TABLE points(
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          client_point_id TEXT UNIQUE,
          latitude REAL NOT NULL,
          longitude REAL NOT NULL,
          accuracy REAL,
          altitude REAL,
          speed REAL,
          battery INTEGER,
          recorded_at TEXT NOT NULL
        )
      ''');
    });
    return _db!;
  }

  Future<void> enqueue(Map<String, dynamic> point) async {
    final d = await db;
    await d.insert('points', point, conflictAlgorithm: ConflictAlgorithm.ignore);
  }

  Future<List<Map<String, dynamic>>> pending({int limit = 50}) async {
    final d = await db;
    return d.query('points', orderBy: 'id ASC', limit: limit);
  }

  Future<void> removeIds(List<int> ids) async {
    if (ids.isEmpty) return;
    final d = await db;
    final placeholders = List.filled(ids.length, '?').join(',');
    await d.delete('points', where: 'id IN ($placeholders)', whereArgs: ids);
  }

  Future<int> count() async {
    final d = await db;
    final r = await d.rawQuery('SELECT COUNT(*) AS c FROM points');
    return (r.first['c'] as int?) ?? 0;
  }
}
