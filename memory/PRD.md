# PRD — Sistem Monitoring IB & Quick Check

## Problem Statement (Ringkasan)
Aplikasi internal TNI untuk monitoring posisi perangkat personel selama IB (Izin Bermalam) dan Quick Check. Stack yang dipilih user: PHP 8.x REST API + MySQL/MariaDB + vanilla HTML/CSS/JS (Leaflet) + Flutter mobile. Server = source of truth untuk tracking_required. 12 tabel fondasi, device approval via admin (tanpa OTP/SMS/password personel), overlapping monitoring, offline queue SQLite, geofence alert ENTER/INSIDE/EXIT, role-based authorization di backend.

**Keputusan user (via ask_human, Juni 2026):** kode PHP+MySQL lengkap sebagai source code (tidak dijalankan di environment Emergent karena platform tidak menyediakan PHP/MySQL), Flutter sebagai source code siap-build, Web Admin vanilla HTML/CSS/JS murni.

## Lokasi Kode
`/app/project/` — backend/ (PHP API), web/ (admin), mobile/ (Flutter), database schema di backend/database/.

## User Personas
- ADMIN: seluruh sistem + manajemen akun.
- KOMANDAN/WADAN: semua personel/perangkat/monitoring/alert/riwayat.
- DANKI: scope Kompinya. DANTON: scope Peletonnya.
- ANGGOTA: hanya aplikasi mobile.

## Sudah Diimplementasikan (2026-06, Phase 1–6 dalam satu delivery)
- Database schema 12 tabel + auth_tokens, index & FK sesuai brief (backend/database/schema.sql).
- Seed organisasi (seed.sql) + seed akun via password_hash (seed.php).
- PHP REST API: front controller, Router, Request/Response JSON standard, AuthMiddleware (user token), DeviceAuthMiddleware (device token), Scope (authorization backend DANKI/DANTON).
- Endpoint lengkap sesuai brief bagian 37 + tambahan minimal (users CRUD, audit-logs, organizations, reports).
- Device registration PENDING → approve (device token) → revoke/replacement; penolakan NRP dengan device ACTIVE.
- Monitoring IB (satu session multi personel) + Quick Check (start now + durasi); lifecycle SCHEDULED→ACTIVE→COMPLETED berdasar waktu server; overlapping tracking_required.
- Location sync: batch, validasi, idempotency client_point_id (INSERT IGNORE), link location_sessions (multi session), geofence check ENTER/INSIDE (throttle 15 mnt)/EXIT, received_at server.
- Device events (whitelist jenis event), battery tracking, last_seen → ONLINE/TERLAMBAT/OFFLINE.
- Web admin vanilla JS: Login, Dashboard (cards + Leaflet map + active monitoring), Personel (search/filter/pagination/import CSV preview+commit/add/edit), Perangkat (pending approve/reject, revoke dengan konfirmasi), Monitoring (buat IB, quick check, detail map polling 12 dtk), Alert (filter, acknowledge/resolve, lihat map), Area Terlarang (map click picker), Riwayat (route polyline + stats + alert), Laporan (CSV/Excel + print PDF), Pengaturan (ganti password, users, audit log).
- Flutter source: aktivasi NRP + poll approval, one-time setup (permission + test), home STANDBY/TRACKING/REVOKED, background service (flutter_background_service), GPS 30 dtk, SQLite offline queue, batch sync, device events, connectivity listener.

## Belum / Backlog
- P0: Uji coba di server nyata (PHP+MySQL) — environment ini tidak bisa menjalankannya; syntax PHP belum ter-lint.
- P0: Build & uji Flutter di mesin dengan Flutter SDK (`flutter create .` untuk melengkapi file platform).
- P1: Validasi lapangan 5–10 perangkat (pilot) sesuai brief bagian 41.
- P1: Cron retention GPS 90 hari (SQL sudah disiapkan di README).
- P2: Import .xlsx native (saat ini Save As → CSV), export PDF server-side (saat ini print browser).

## Next Tasks
1. Deploy ke server PHP 8 + MySQL, jalankan schema/seed, smoke test endpoint (curl).
2. Build APK, uji alur aktivasi → approve → tracking → offline sync.
3. Bug fixing dari hasil pilot.
