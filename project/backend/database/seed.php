<?php
// Seed akun awal. Jalankan: php database/seed.php
// Password dibuat via password_hash() sesuai requirement.

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../config/database.php';

$pdo = Database::pdo();

$users = [
    // name, username, password, role, organization_id
    ['Administrator', 'admin',     'admin123',    'ADMIN',    null],
    ['Komandan Batalyon', 'komandan', 'komandan123', 'KOMANDAN', 1],
    ['Wadan Batalyon', 'wadan',    'wadan123',    'WADAN',    1],
    ['Danki A', 'danki.a',         'danki123',    'DANKI',    2],
    ['Danton A1', 'danton.a1',     'danton123',   'DANTON',   4],
];

$stmt = $pdo->prepare(
    'INSERT INTO users (name, username, password_hash, role, organization_id)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name)'
);

foreach ($users as $u) {
    $hash = password_hash($u[2], PASSWORD_DEFAULT);
    $stmt->execute([$u[0], $u[1], $hash, $u[3], $u[4]]);
    echo "User {$u[1]} ({$u[3]}) siap.\n";
}

echo "Selesai. SEGERA ganti password default setelah login pertama.\n";
