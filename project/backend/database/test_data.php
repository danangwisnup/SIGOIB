<?php
// Test data minimal untuk pilot/smoke test. Idempotent (aman dijalankan ulang).
// Jalankan SETELAH schema.sql + seed.sql + seed.php:
//   php database/test_data.php

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::pdo();

$personnel = [
    // nrp, name, rank, position, company_id, platoon_id
    ['320001', 'Budi Santoso',  'Serka', 'Ba Intel',   2, 4],
    ['320002', 'Andi Wijaya',   'Kopda', 'Tamtama',    2, 4],
    ['320003', 'Citra Dewi',    'Letda', 'Danton A1',  2, 4],
    ['320004', 'Dedi Kurnia',   'Sertu', 'Tamtama',    2, 5],
    ['320005', 'Eko Prasetyo',  'Koptu', 'Tamtama',    2, 5],
    ['320006', 'Fajar Nugroho', 'Serda', 'Ba Log',     3, 6],
    ['320007', 'Gilang Ramadhan','Kopda','Tamtama',    3, 6],
    ['320008', 'Hadi Saputra',  'Letda', 'Danton B2',  3, 7],
];

$pstmt = $pdo->prepare(
    'INSERT IGNORE INTO personnel (nrp, name, rank, position, company_id, platoon_id)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$count = 0;
foreach ($personnel as $p) {
    $pstmt->execute($p);
    $count += $pstmt->rowCount();
}
echo "Personel: $count baru dari " . count($personnel) . " (sisanya sudah ada).\n";

// Satu geofence contoh (Monas, radius 300 m) — idempotent by name
$g = $pdo->prepare('SELECT id FROM geofences WHERE name = ?');
$g->execute(['Area Terlarang Contoh']);
if (!$g->fetch()) {
    $pdo->prepare(
        'INSERT INTO geofences (name, category, latitude, longitude, radius, created_by)
         VALUES (?, ?, ?, ?, ?, NULL)'
    )->execute(['Area Terlarang Contoh', 'Tempat Hiburan', -6.1753924, 106.8271528, 300]);
    echo "Geofence contoh dibuat.\n";
}

// Satu device PENDING untuk NRP 320001 (uji approval) — idempotent by uuid
$d = $pdo->prepare('SELECT id FROM devices WHERE device_uuid = ?');
$d->execute(['test-device-uuid-0001']);
if (!$d->fetch()) {
    $pid = $pdo->prepare('SELECT id FROM personnel WHERE nrp = ?');
    $pid->execute(['320001']);
    $row = $pid->fetch();
    if ($row) {
        $pdo->prepare(
            'INSERT INTO devices (personnel_id, device_uuid, platform, model, app_version, status)
             VALUES (?, ?, ?, ?, ?, "PENDING")'
        )->execute([$row['id'], 'test-device-uuid-0001', 'android', 'Test Device', '1.0.0']);
        echo "Device PENDING test dibuat (NRP 320001).\n";
    }
}

echo "Test data siap.\n";
