# Sistem Monitoring IB & Quick Check

Aplikasi internal untuk monitoring posisi perangkat personel selama IB (Izin Bermalam) dan Quick Check (Monitoring Cepat).

- **Backend**: PHP 8.x REST API (PDO prepared statements, tanpa framework)
- **Database**: MySQL 8.x / MariaDB 10.4+ (12 tabel fondasi + `auth_tokens`)
- **Web Admin**: HTML + CSS + Vanilla JavaScript + Leaflet.js (OpenStreetMap)
- **Mobile**: Flutter (Android & iOS) — source code di `mobile/`
- **Server = source of truth**: mobile hanya mengikuti `tracking_required` dari server.

## Struktur Project

```
project/
├── backend/
│   ├── api/               # front controller (index.php) + .htaccess
│   ├── config/            # env loader + koneksi PDO
│   ├── controllers/       # AuthController, PersonnelController, dst.
│   ├── core/              # Router, Request, Response (JSON standard)
│   ├── middleware/        # AuthMiddleware, DeviceAuthMiddleware, Scope (authorization)
│   ├── models/            # 12 tabel + auth_tokens
│   ├── services/          # SessionService, TrackingService, GeofenceService, ImportService, AuditService
│   ├── database/          # schema.sql, seed.sql, seed.php
│   └── .env.example
├── web/
│   ├── index.php          # shell web admin
│   └── assets/            # css + js (vanilla, Leaflet via CDN)
├── mobile/                # Flutter project (aktivasi NRP, background GPS, offline queue)
├── samples/personnel_template.csv
├── router.php             # dev server PHP built-in
└── .htaccess              # routing /api untuk Apache
```

## Cara Menjalankan (Server Anda)

### 1. Database

```bash
mysql -u root -p < backend/database/schema.sql
mysql -u root -p < backend/database/seed.sql
```

### 2. Konfigurasi

```bash
cp backend/.env.example backend/.env
# edit DB_HOST, DB_NAME, DB_USER, DB_PASS
```

### 3. Seed akun awal + test data

```bash
cd backend
php database/seed.php          # akun admin/komandan/wadan/danki/danton
php database/test_data.php     # 8 personel + 1 geofence + 1 device PENDING (idempotent)
```

Tanpa SSH (cPanel/phpMyAdmin): import `backend/database/seed_users.sql` dan (opsional) `backend/database/test_data.sql`.

### 3b. Smoke test otomatis (opsional, butuh server dev berjalan)

```bash
bash scripts/smoke_test.sh     # 66 skenario API end-to-end
```

> **Timezone:** backend menyelaraskan timezone koneksi MySQL dengan `APP_TIMEZONE` di `.env` secara otomatis (penting agar `NOW()`, expiry token, dan throttle alert konsisten).

Akun default (SEGERA ganti setelah login pertama):

| Role | Username | Password |
|---|---|---|
| ADMIN | admin | admin123 |
| KOMANDAN | komandan | komandan123 |
| WADAN | wadan | wadan123 |
| DANKI | danki.a | danki123 |
| DANTON | danton.a1 | danton123 |

### 4. Jalankan

**Development (PHP built-in server):**

```bash
cd project
php -S 0.0.0.0:8000 router.php
# Web admin: http://localhost:8000/
# API:       http://localhost:8000/api/...
```

**Production (Apache)**: arahkan docroot ke folder `project/` (`.htaccess` sudah disiapkan; pastikan `mod_rewrite` aktif dan `AllowOverride All`).

**Production (cPanel / Nginx / HTTPS / checklist lengkap): lihat [DEPLOYMENT.md](DEPLOYMENT.md).**

**Production (Nginx)**:

```nginx
location /api/ { fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME /path/project/backend/api/index.php; include fastcgi_params; }
location / { root /path/project/web; try_files $uri $uri/ /index.php; }
```

### 5. Mobile (Flutter)

```bash
cd mobile
flutter pub get
flutter build apk --dart-define=API_BASE_URL=https://server-anda.example.com
# atau: flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000
```

Catatan build: setelah `flutter create .` di folder `mobile/` untuk melengkapi file platform (gradle, xcodeproj) yang tidak disertakan di sini, lalu timpa `lib/`, `pubspec.yaml`, `AndroidManifest.xml`, `Info.plist` dengan file dari repo ini. Lihat `mobile/README.md`.

## Konsep Tracking

```
TRACKING REQUIRED = DEVICE ACTIVE AND (ACTIVE IB OR ACTIVE QUICK CHECK)
```

- Tidak ada monitoring aktif → STANDBY, GPS OFF, polling ringan ~60 detik.
- Ada monitoring aktif → TRACKING, GPS ~30 detik, batch sync 5–10 point per request.
- IB + Quick Check overlap → satu GPS stream; tracking tetap ON selama masih ada SATU session aktif.
- Offline → GPS tetap jalan, data masuk SQLite queue, batch sync saat internet kembali (idempotency via `client_point_id` — duplicate tidak membuat record ganda).
- Status session (SCHEDULED → ACTIVE → COMPLETED) dihitung server dari waktu server di setiap request.

## Endpoint API

Semua response JSON: `{"success": true, "data": {...}}` atau `{"success": false, "message": "..."}`.

| Endpoint | Method | Auth | Keterangan |
|---|---|---|---|
| /api/auth/login | POST | - | Login web admin (username+password) |
| /api/auth/logout | POST | Bearer user | Logout |
| /api/auth/me | GET | Bearer user | Profil |
| /api/auth/password | PUT | Bearer user | Ganti password |
| /api/device/register | POST | - | Mobile: registrasi NRP + device_uuid → PENDING |
| /api/device/status | GET | Bearer device / ?device_uuid= | device_status, tracking_required, interval, active_sessions |
| /api/device/event | POST | Bearer device | APP_STARTED, TRACKING_STARTED, dll |
| /api/personnel | GET/POST | Bearer user | List (search/filter/pagination), tambah |
| /api/personnel/{id} | GET/PUT | Bearer user | Detail / edit |
| /api/personnel/import | POST | Bearer user | Bulk import CSV (mode=preview/commit) |
| /api/devices | GET | Bearer user | List perangkat |
| /api/devices/pending | GET | Bearer user | Antrian approval |
| /api/devices/{id}/approve | POST | ADMIN/KOMANDAN/WADAN | Approve → generate device token |
| /api/devices/{id}/reject | POST | ADMIN/KOMANDAN/WADAN | Tolak pending |
| /api/devices/{id}/revoke | POST | ADMIN/KOMANDAN/WADAN | Revoke (ganti HP) |
| /api/monitoring | GET | Bearer user | List session |
| /api/monitoring/ib | POST | ADMIN/KOMANDAN/WADAN | Buat IB (satu session, multi personel) |
| /api/monitoring/quick-check | POST | ADMIN/KOMANDAN/WADAN | Quick check (start now + durasi) |
| /api/monitoring/{id} | GET/PUT | Bearer user | Detail / edit |
| /api/monitoring/{id}/cancel | POST | ADMIN/KOMANDAN/WADAN | Cancel |
| /api/monitoring/{id}/locations | GET | Bearer user | Marker map (polling 10–15 dtk) |
| /api/location/sync | POST | Bearer device | Batch GPS, idempotent, geofence check |
| /api/geofences | GET/POST | Bearer user | List / buat area terlarang |
| /api/geofences/{id} | PUT/DELETE | ADMIN/KOMANDAN/WADAN | Edit / hapus |
| /api/alerts | GET | Bearer user | List alert (scoped) |
| /api/alerts/{id}/status | PUT | Bearer user | OPEN → ACKNOWLEDGED/RESOLVED |
| /api/dashboard | GET | Bearer user | Statistik cards |
| /api/dashboard/locations | GET | Bearer user | Marker dashboard |
| /api/history/personnel/{id} | GET | Bearer user | Route history + alert |
| /api/reports/monitoring/{id} | GET | Bearer user | Ringkasan; `?format=csv` untuk Excel |
| /api/organizations | GET | Bearer user | Struktur organisasi (dropdown) |
| /api/users | GET/POST | ADMIN | Manajemen akun |
| /api/users/{id} | PUT | ADMIN | Edit akun |
| /api/audit-logs | GET | ADMIN/KOMANDAN | Jejak tindakan admin |

## Authorization (Backend)

Scope diterapkan di backend (`middleware/Scope.php`), bukan hanya menyembunyikan menu:

- ADMIN / KOMANDAN / WADAN → seluruh data.
- DANKI → hanya personel/perangkat/monitoring/alert Kompinya.
- DANTON → hanya scope Peletonnya.
- ANGGOTA → tidak punya akun web; hanya aplikasi mobile.

## Role Perangkat & Geofence

- Satu personel hanya boleh punya SATU device ACTIVE (registrasi kedua ditolak dengan pesan sesuai brief).
- Ganti HP: admin revoke device lama → registrasi HP baru → PENDING → approve.
- Geofence: circle (lat/lng/radius). Server menghitung ENTER / INSIDE (throttle 15 mnt) / EXIT per device dari dua titik GPS berurutan → membuat alert.
- Status koneksi: < 2 mnt ONLINE, 2–5 mnt TERLAMBAT, > 5 mnt OFFLINE (tanpa menyimpulkan penyebab).

## Retensi Data

GPS detail ~90 hari (jalankan via cron harian; `location_sessions` ikut terhapus via FK CASCADE):

```sql
DELETE FROM locations WHERE received_at < NOW() - INTERVAL 90 DAY;
```

Historical device, audit log, dan alert TIDAK ikut dihapus.

## Checklist Test (urutan dari brief)

1. Login admin → dashboard tampil.
2. Import `samples/personnel_template.csv` (preview → import; cek validasi NRP duplicate / Kompi invalid).
3. Mobile: registrasi NRP → PENDING → admin approve → device token diterima → setup → siap.
4. Registrasi NRP yang sama di HP lain → ditolak ("NRP ini sudah terdaftar...").
5. Revoke device lama → registrasi HP baru (device replacement).
6. Buat IB (SCHEDULED) → otomatis ACTIVE saat waktu mulai → device menerima `tracking_required=true`.
7. Quick Check 30 menit → overlap dengan IB → tracking ON → QC selesai, IB aktif → tetap ON → IB selesai → STANDBY.
8. GPS sync batch; matikan internet → queue; nyalakan → sync tanpa duplicate (kirim ulang batch sama → `duplicated`).
9. Buat geofence → gerakkan device masuk/di dalam/keluar → alert ENTER/INSIDE/EXIT.
10. Riwayat: pilih personel + tanggal → route map + statistik.
11. Login `danki.a` → hanya data Kompi A; `danton.a1` → hanya Peleton A1; akses API di luar scope → 403/404.
12. Token invalid/revoked → 401/403.

## Batasan yang Disengaja (MVP)

- File Excel (.xlsx) → gunakan "Save As → CSV" (parsing xlsx tanpa library tidak dilakukan).
- Export PDF → tombol Print (Save as PDF) di halaman Laporan; CSV untuk Excel.
- Background tracking mengikuti kemampuan OS (force-stop/reboot dapat menghentikan; Android pakai foreground service + notifikasi, iOS pakai background location capability).
