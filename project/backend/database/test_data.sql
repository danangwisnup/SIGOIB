-- Test data minimal (versi SQL murni untuk phpMyAdmin, idempotent).
-- Jalankan SETELAH schema.sql + seed.sql. Opsional — untuk uji/pilot saja.

USE monitoring_ib;

INSERT IGNORE INTO personnel (nrp, name, rank, position, company_id, platoon_id) VALUES
  ('320001', 'Budi Santoso',   'Serka', 'Ba Intel',  2, 4),
  ('320002', 'Andi Wijaya',    'Kopda', 'Tamtama',   2, 4),
  ('320003', 'Citra Dewi',     'Letda', 'Danton A1', 2, 4),
  ('320004', 'Dedi Kurnia',    'Sertu', 'Tamtama',   2, 5),
  ('320005', 'Eko Prasetyo',   'Koptu', 'Tamtama',   2, 5),
  ('320006', 'Fajar Nugroho',  'Serda', 'Ba Log',    3, 6),
  ('320007', 'Gilang Ramadhan','Kopda', 'Tamtama',   3, 6),
  ('320008', 'Hadi Saputra',   'Letda', 'Danton B2', 3, 7);

-- Satu geofence contoh (Monas, radius 300 m)
INSERT INTO geofences (name, category, latitude, longitude, radius, created_by)
SELECT 'Area Terlarang Contoh', 'Tempat Hiburan', -6.1753924, 106.8271528, 300, NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM geofences WHERE name = 'Area Terlarang Contoh');

-- Satu device PENDING untuk NRP 320001 (uji approval)
INSERT INTO devices (personnel_id, device_uuid, platform, model, app_version, status)
SELECT (SELECT id FROM personnel WHERE nrp = '320001'),
       'test-device-uuid-0001', 'android', 'Test Device', '1.0.0', 'PENDING'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM devices WHERE device_uuid = 'test-device-uuid-0001')
  AND EXISTS (SELECT 1 FROM personnel WHERE nrp = '320001');
