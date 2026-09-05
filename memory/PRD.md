# PRD — Sistem Monitoring IB & Quick Check

## Problem Statement (Ringkasan)
Aplikasi internal TNI untuk monitoring posisi perangkat personel selama IB (Izin Bermalam) dan Quick Check. Stack dipilih user: PHP 8.x REST API + MySQL/MariaDB + vanilla HTML/CSS/JS (Leaflet) + Flutter mobile. Server = source of truth untuk tracking_required. 12 tabel fondasi, device approval via admin (tanpa OTP/SMS/password personel), overlapping monitoring, offline queue SQLite, geofence alert ENTER/INSIDE/EXIT, role-based authorization di backend.

**Keputusan user:** kode PHP+MySQL lengkap di `/app/project/`; Flutter sebagai source code siap-build; Web Admin vanilla murni.

## Lokasi Kode
`/app/project/` — backend/ (PHP API), web/ (admin), mobile/ (Flutter), backend/database/ (schema+seed+test_data), scripts/smoke_test.sh (66 skenario).

## User Personas
- ADMIN: seluruh sistem + manajemen akun. KOMANDAN/WADAN: semua data. DANKI: scope Kompi. DANTON: scope Peleton. ANGGOTA: hanya mobile.

## Terimplementasi & Terverifikasi

### Phase 1–6 (2026-06): build awal
Database 12 tabel + auth_tokens, REST API lengkap (37 endpoint), web admin 9 halaman, Flutter mobile source (aktivasi NRP, setup, background GPS, SQLite offline queue, batch sync).

### Phase 7 (2026-06): Integration test & hardening — SELESAI
**Environment test nyata dipasang di pod: PHP 8.2 CLI + MariaDB + `php -S 127.0.0.1:8899 router.php`.**

PASS (terverifikasi runtime):
- 66/66 skenario smoke API (`scripts/smoke_test.sh`): auth+401, import CSV preview/commit+validasi, device lifecycle (register→PENDING→approve→ACTIVE, single-ACTIVE 409, revoke→403, replacement), IB lifecycle SCHEDULED→ACTIVE→COMPLETED via server time, location sync batch + idempotency client_point_id (retry → duplicated, 0 duplikat), personnel_id dari mobile diabaikan, geofence ENTER/INSIDE(throttle 15 mnt)/EXIT, quick check overlap CASE A–D, dashboard, history, report JSON+CSV, role scope DANKI/DANTON di backend (403/404), audit log lengkap.
- Testing agent (iteration_1.json): 16/17 backend targeted + semua alur UI 9 halaman tanpa console error (login, personel, perangkat approve/revoke via UI, buat IB + detail + cancel, quick check, geofence modal map picker, laporan + download CSV, pengaturan, logout).

FIXED di Phase 7:
1. Timezone mismatch PHP(Asia/Jakarta) vs MySQL NOW() → `SET time_zone` per koneksi di `config/database.php` (memperbaiki throttle INSIDE & konsistensi expiry token).
2. Throttle INSIDE gagal → bagian dari fix #1 (terverifikasi tidak spam).
3. Revoke men-null-kan token → device revoked mendapat 401 ambigu; kini token dipertahankan, middleware menolak dengan 403 + pesan jelas.
4. Export CSV via `<a href>` tanpa Authorization → diubah ke authenticated fetch (`UI.downloadFile`).
5. `router.php` salah mapping path static (`/web/web/...`).
6. Validasi range koordinat/radius geofence.
7. Re-register device revoked dibatasi personel yang sama.
8. Double-cancel monitoring diterima → kini 409 "Monitoring sudah dibatalkan sebelumnya." (juga 409 untuk COMPLETED).
9. Test data idempotent: `backend/database/test_data.php` (8 personel, geofence, 1 device PENDING).
10. `pubspec.yaml`: dependency eksplisit `flutter_background_service_android`.

WARNING:
- DB credentials dev (`monitoring`/`mon1torING_dev`) ada di `backend/.env` lokal pod ini — hanya untuk test; produksi wajib ganti.
- MariaDB tz lokal vs OS UTC: saat test API manual, gunakan waktu Asia/Jakarta.

NOT TESTABLE HERE:
- Build/run Flutter (`flutter pub get`, analyze, build apk) — butuh Flutter SDK; source sudah static-reviewed.
- Background GPS riil Android/iOS (foreground service, battery optimization) — butuh perangkat fisik/emulator; ikuti pilot 5–10 perangkat.

### Phase 8 (2026-06): Deployment package — SELESAI
- `DEPLOYMENT.md` baru: panduan cPanel (File Manager, PHP 8.2 selector, MySQL Wizard, import via phpMyAdmin, AutoSSL/HTTPS, cron retensi), VPS Apache/Nginx, hardening produksi (ganti password default & credential DB, CORS note), checklist SERVER/WEB/MOBILE.
- `backend/database/seed_users.sql` + `test_data.sql`: seeding tanpa SSH (hash bcrypt terverifikasi runtime) untuk phpMyAdmin.
- Fix `.htaccess` root: pola RedirectMatch konteks .htaccess (tanpa leading slash) agar blokir `backend/` benar-benar aktif di Apache/cPanel.
- `.gitignore` (backend/.env dilarang ikut paket).
- `mobile/README.md`: flutter create/pub get/analyze/build apk --release/build ios --release + penjelasan permission Android & iOS.
- Terverifikasi: seed SQL di-load ke MariaDB bersih → login admin via API PASS; health check endpoint 405 JSON.

### Phase 8.1 (2026-09): Fix flutter analyze — SELESAI
- `pubspec.yaml`: tambah dev_dependencies `flutter_test` (sdk) + `flutter_lints ^5.0.0`; hapus direct dep `flutter_background_service_android` (simbol `AndroidServiceInstance` sudah dire-export oleh `flutter_background_service` — direct dep juga berisiko konflik versi 6.x vs 5.x).
- `analysis_options.yaml` (BARU): `include: package:flutter_lints/flutter.yaml`.
- `test/widget_test.dart` (BARU): test parsing `DeviceStatus` (menggantikan template bawaan `flutter create` yang mereferensikan MyApp tidak ada).
- `background_service.dart`: hapus unnecessary import flutter_background_service_android.
- `tracking_controller.dart`: hapus field `_connSub` yang tidak pernah dipakai (subscription tetap aktif, logic tracking tidak berubah).
- Hasil nyata di pod (Flutter 3.47.2 stable): `flutter pub get` OK (106 deps), `flutter analyze` → **No issues found!**, `flutter test` → model test PASS.

### Phase 9 (2026-09): WEB2 admin (PHP SSR) + React Native mobile + fix reinstall — SELESAI
- **`/web2`** BARU: admin panel PHP server-side rendering murni (bukan SPA, vanilla JS minimal untuk Leaflet/sidebar/modal/countdown). Struktur sesuai brief: 15 halaman + includes/ (config, api cURL client, auth session+CSRF, functions, header/sidebar/topbar/footer, picker) + assets css/js. Semua data via API existing; token API disimpan server-side di PHP session. Auto-refresh 10 dtk (meta refresh, filter GET terjaga) hanya di dashboard/monitoring/alerts. Konfirmasi aksi berisiko via modal sendiri (bukan alert()). Design system: deep forest green/army/charcoal/off-white + aksen gold.
- **Fix reinstall (bagian 18)**: `DevicePublicController::register` — device_uuid yang sudah ACTIVE untuk NRP yang SAMA kini menerbitkan token baru (perangkat fisik sama terdeteksi via hardware-stable ID); NRP berbeda → 409; perangkat baru NRP sama → tetap PENDING (approval). Terverifikasi curl: reinstall→token baru, NRP lain→409, device baru→PENDING. Smoke 66/66 tetap PASS (tanpa regresi).
- **`/mobile-rn`** BARU: aplikasi mobile React Native + TypeScript (Flutter tetap utuh di /mobile sebagai legacy). Stack: react-native-keychain (token), react-native-sqlite-storage (offline queue + client_point_id), react-native-background-actions (foreground service), geolocation-service, netinfo, permissions, device-info (hardware-stable uuid). `npx tsc --noEmit` PASS (0 error). README.md ditulis ulang sebagai panduan instalasi lengkap untuk pemula (prasyarat Node/JDK17/Android SDK, env var, init rangka android/ios, edit buildConfig.ts, run/build APK, iOS di Mac, tabel troubleshooting).
- Infrastruktur test pod: PHP_CLI_SERVER_WORKERS=8 (menghindari deadlock sub-request web2→API pada php -S single-worker), php-curl terpasang.
- API LIMITATION dicatat di personnel.php: filter Pangkat & status perangkat bukan parameter API existing.
- WEB2 acceptance (curl): 12/12 halaman 200 setelah login, redirect bekerja, konten dashboard termuat.
- Testing agent iteration_2 (web2): ~92% (17/18 flow). Badge double-escape terverifikasi hilang, meta-refresh policy benar, DANKI scope 6 personel, CSV proxy aman, drawer mobile OK.
- Fix lanjutan dari iteration_2: `includes/auth.php` kini require `functions.php` (sebelumnya POST handler 500 karena set_flash/fmt_* belum ter-load → flash duplicate-NRP/IB tidak tampil; terverifikasi ulang via curl: flash OK, IB save OK); `.modal` diberi z-index eksplisit di atas backdrop. Catatan: dashboard memang 10 kartu (2 baris) — klaim "5 kartu" adalah salah ukur agent; semua tabel lebar sudah dibungkus `.table-scroll`. Backend double-cancel 409 sudah diperbaiki sejak Phase 7 (terverifikasi curl).

## Backlog
- P0: Deploy ke cPanel produksi ikut DEPLOYMENT.md (ganti kredensial, HTTPS, ganti password default).
- P0: Build & uji APK pada 5–10 perangkat (pilot) — termasuk app minimized, screen locked, phone restart.
- P1: Cron retensi GPS 90 hari (SQL tersedia di README).
- P2: Import .xlsx native; PDF server-side.

## Next Tasks
1. Deploy produksi + ganti password default semua akun.
2. Pilot 5–10 perangkat, kumpulkan device_events, tuning interval.
3. Deployment penuh setelah pilot stabil.
