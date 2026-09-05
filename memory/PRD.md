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

### Phase 10 (2025-07): Audit production-ready — fix force close mobile + web2 revoke — SELESAI (kode)
ROOT CAUSE force close mobile (setelah approval & saat reopen):
- `App.tsx` lama memanggil `startServices()` (termasuk `BackgroundService.start()`) TANPA cek status server, langsung saat token ada. Di Android 14 (targetSdk 34) `react-native-background-actions` 4.0.1 tidak mengirim `foregroundServiceType` (baru 4.1.0) → bila manifest tak deklarasikan tipe service, `start()` melempar `ForegroundServiceStartNotAllowedException`/`MissingForegroundServiceTypeException` di NATIVE (tak tertangkap try/catch JS) → force close. Karena startup selalu start FGS saat token ada → reopen crash lagi (crash-loop).
FIX (TS, `tsc --noEmit` PASS):
- `App.tsx` di-rewrite jadi state machine SERVER-AUTHORITATIVE: LOADING→(no token)ACTIVATION / (token) initTracking + tanya `statusByToken` → REVOKED/PENDING/SETUP/HOME; offline/error → state lokal aman, tidak crash; 401/403 → RevokedScreen.
- FGS TIDAK pernah distart di startup. `trackingController` hanya start FGS saat `device_status=ACTIVE && tracking_required` dan dari foreground → tepat setelah approval (belum ada sesi) FGS tak distart → tidak crash. Guard `BackgroundService.isRunning()`/start/stop, Geolocation, NetInfo.
- `secure.ts` (Keychain) & `queue.ts` (SQLite) dibungkus try/catch: gagal buka DB / baca token → degrade, bukan crash.
- `PendingScreen.tsx` baru (token ada tapi server PENDING). `RevokedScreen` reactivation → `clearForReactivation()` (stop FGS + clear token) → ActivationScreen.
- Single GPS stream dipertahankan (satu boolean `tracking`; overlap IB+QuickCheck tidak membuat stream kedua; server yang menentukan).
- `NATIVE_ANDROID_CHANGES.md` (BARU): perubahan manifest WAJIB (service `RNBackgroundActionsTask` + `foregroundServiceType="location"`, permission FOREGROUND_SERVICE_LOCATION/POST_NOTIFICATIONS/WAKE_LOCK/background location, target/compileSdk). TIDAK regenerate android/ios (folder native ada di build user).
WEB2 revoke:
- Route `/api/devices/{id}/revoke` + handler `.confirm-form`+modal + role (ADMIN∈WEB2_MANAGE_ROLES) + z-index semua BENAR di source. Tidak ditemukan bug pemutus definitif dari source statis. Diperkeras defensif di `includes/footer.php`: konfirmasi diubah ke EVENT DELEGATION di document + guard elemen modal + fallback submit native (tombol tak pernah "mati" walau ada error JS/timing).
KETERBATASAN VERIFIKASI: pod ini TIDAK menjalankan PHP/MySQL (atas permintaan user) dan TIDAK ada Android SDK/emulator (folder native tidak diregenerate). Build APK, emulator, dan flow web2 runtime BELUM DAPAT DIVERIFIKASI di sini; hanya `tsc` mobile PASS + audit source. Perubahan manifest native harus diterapkan user di project build-nya.

### Phase 11 (2025-07): WEB2 control-center UX + no-reload live polling — SELESAI (kode, lint PASS)
Prinsip: HANYA /web2 + mobile; TANPA API/DB baru; /web tidak disentuh. Backend online/offline sudah dinamis (Device::onlineStatus 2m/5m dari last_seen_at; touchSeen=heartbeat) → web2 menurunkan status dari data device existing.
BARU: `web2/api/live.php` (feed=monitoring|dashboard|alerts|devices, JSON same-origin, proxy session ke API existing, token tetap server-side) + `web2/api/action.php` (approve/reject/revoke device, alert status, cancel monitoring — POST+CSRF, role di-enforce) + `assets/js/live.js` (poll 1 timer/halaman, pause saat tab hidden via Page Visibility, toast, ago/connFromSeen/esc).
HAPUS meta-refresh (header.php) → semua auto-update via fetch async 10 dtk (dashboard/monitoring/alerts/devices). Tidak ada location.reload/meta refresh (grep bersih).
Leaflet: `web2LiveMap` (dibuat SEKALI) + `web2UpsertMarkers` (registry per personnel_id, marker di-update bukan recreate) + `web2FocusMarker`. Popup + list punya tombol BUKA DI GOOGLE MAPS (koordinat aktual, tidak hardcode).
monitoring.php: control-center split (kiri daftar personel + search/filter Kompi/Peleton/status client-side, kanan peta besar); klik baris → fokus marker. dashboard.php: stat cards live (ONLINE/TERLAMBAT/OFFLINE terpisah, TRACKING, IB/QC aktif, AREA TERLARANG, perangkat) + peta + sesi/alert live. alerts.php: list + ack/resolve async. devices.php: pending dinamis (poll → badge/banner + insert row + toast) + REVOKE modal konfirmasi kaya (Nama/NRP/Platform/Model) + async (loading, update row tanpa reload); form POST tetap sbg fallback non-JS. history.php: polyline + daftar titik (WAKTU|POSISI|STATUS, klik→fokus) + Google Maps titik awal/akhir/tiap titik.
Verifikasi statis: `php -l` semua web2 PASS; `node --check` live.js/map.js PASS; inline JS 5 halaman syntax PASS; mobile `tsc --noEmit` PASS; single GPS stream (1 pemanggil startBackgroundTracking, guard tracking&&!bgRunning); idempotency client_point_id + INSERT OR IGNORE.
BELUM DAPAT DIVERIFIKASI RUNTIME (batasan): backend PHP+MySQL/DB existing tidak dijalankan di pod → alur browser (login, polling live, revoke async, map) belum diuji runtime; hanya audit + lint. Mobile build/emulator tetap butuh Android SDK.

### Phase 12 (2025-07): WEB2 Monitoring UX final refinement (control-center inline route) — SELESAI (kode, static lint PASS)
Prinsip: HANYA /web2 (monitoring.php, api/live.php, assets/js/map.js, assets/css/components.css). TANPA API/DB/backend/mobile/router baru. Bangun di atas Phase 11, ubah seminimal mungkin.
INTI: Monitoring jadi pusat; klik personel → detail + PERJALANAN tampil INLINE (drawer di atas peta) TANPA pindah ke history.php.
- `api/live.php` feed=monitoring DIROMBAK jadi MERGE sisi-web2 (disetujui user): (1) `/personnel` (paginate per_page=100, maks 20 hlm) = daftar dasar SEMUA personel scope → Monitoring TIDAK PERNAH kosong walau tanpa sesi aktif; (2) `/devices` ACTIVE → status koneksi (ONLINE/TERLAMBAT/OFFLINE) & baterai INDEPENDEN sesi; (3) `/dashboard/locations` → koordinat posisi terakhir (hanya personel sesi AKTIF) + active_sessions; (4) `/monitoring/{id}/locations` → map pid→nama sesi utk badge DIMONITOR. Output: people[] (conn, monitored, session_name, has_position, battery, accuracy, last_seen_at, lat/lng bila ada), markers[] (HANYA yg punya koordinat — tidak ada marker palsu), ib_active/qc_active/monitored_count/total_scope. Matriks status penuh terpenuhi: ONLINE/TERLAMBAT/OFFLINE × DIMONITOR/TIDAK DIMONITOR.
- `api/live.php` feed=route BARU: proxy `/history/personnel/{id}` (opsional session_id/date) → points+sessions untuk route inline. Tidak ada endpoint backend baru.
- `monitoring.php` DITULIS ULANG (control-center): banner status sesi LIVE (🟢 IB / 🟠 QUICK CHECK / ⚪ TIDAK ADA SESI + jumlah dimonitor/scope) di atas; split sidebar personel (search realtime multi-field nama/NRP/pangkat/kompi/peleton, seg-filter Semua/Online/Dimonitor/Offline, select Kompi/Peleton, toggle Peta/Daftar) + peta besar; drawer detail kanan (overlay di atas peta) muncul saat klik personel/marker: status ONLINE+DIMONITOR, baterai/akurasi/alert, tombol Google Maps posisi (koordinat aktual), dropdown sesi, dan TIMELINE perjalanan (🔵 Titik Awal → 📍 → 🟢 Sekarang bila dimonitor / 🔴 Titik Akhir bila selesai). Klik titik → map flyTo + popup + Google Maps. Panel "Kelola Sesi" lama jadi <details> sekunder (tetap simpan tabel + cancel fallback + testid). Default peta = marker posisi terakhir SEMUA yg punya koordinat; route hanya tampil saat 1 personel dipilih.
- `map.js` (aditif, tidak merusak dashboard/history): web2ShowRoute/web2ClearRoute (LayerGroup route di map yang SAMA, dibersihkan sebelum gambar baru), web2FocusLatLng, web2RoutePointPopup; web2UpsertMarkers kini bind klik marker → state.onMarkerClick (opsional, guarded); web2MarkerPopup tambah baris Monitoring KONDISIONAL (m.monitored!==undefined) shg dashboard tidak regresi. `components.css` +blok CSS control-center (banner, seg, view-toggle, drawer md-*, chip-mon/unmon, list-mode, mon-manage details).
- Live: 1 timer poll 'monitoring' 10 dtk (live.js, pause saat tab hidden). Map dibuat SEKALI; marker upsert; polyline di-redraw efisien; detail diperbarui via updateDetailLive (tanpa rebuild → tidak berkedip); route personel terpilih (bila dimonitor) di-refresh tiap poll dgn fit:false (peta tidak melompat). TIDAK ada location.reload/meta refresh (grep bersih).
- SERVER TETAP SOURCE OF TRUTH: web2 hanya membaca; membuka Monitoring TIDAK mengaktifkan tracking. Mekanisme IB/Quick Check/overlap/tracking_required TIDAK diubah.
- Verifikasi statis: `node --check` map.js/live.js + JS inline monitoring.php PASS; PHP tag balance OK; audit no-reload/single-timer/map-not-recreated PASS; tidak ada dangling ref; semua web2* fn terpakai terdefinisi.
- BELUM DIVERIFIKASI RUNTIME: pod TIDAK menjalankan PHP/MySQL (atas permintaan user; PHP tidak diinstal). `php -l` tidak dijalankan. Alur browser (login, polling, klik personel, route, Google Maps) akan diuji user di environment lokal/server.

### Phase 13 (2025-07): WEB (SPA vanilla JS) Monitoring → Control Center — SELESAI (kode, static lint PASS)
Prinsip: HANYA `/app/project/web` (SPA vanilla JS, tema biru). Ubah hanya `assets/js/pages/monitoring.js` (rewrite) + append `assets/css/app.css`. TIDAK menyentuh index.php, api.js, ui.js, app.js, halaman lain, backend/API/DB/mobile. Framework tetap vanilla JS. 9 fitur & menu tetap utuh.
MASALAH lama: monitoring.js berbasis daftar-sesi (tabel sesi → klik Detail → map kedua). Diubah jadi control-center kiri list prajurit + kanan map besar.
- MERGE client-side (tanpa proxy/endpoint baru): `/personnel` (paginate per_page=100, base list SEMUA scope → tidak pernah kosong) + `/devices` (status ONLINE<120s/TERLAMBAT≤300s/OFFLINE & baterai, independen sesi) + `/dashboard/locations` (koordinat, hanya sesi aktif + active_sessions + server_time) + per active session `/monitoring/{id}/locations` (membership pid→sesi untuk badge DIMONITOR). Marker HANYA untuk yg punya koordinat nyata (tidak ada marker palsu). Matriks penuh: ONLINE/TERLAMBAT/OFFLINE/TANPA PERANGKAT × DIMONITOR/TIDAK DIMONITOR. conn dari server_time vs last_seen (tz cancel).
- UX: banner status sesi LIVE (🟢 IB/🟠 QC/⚪ none + jumlah dimonitor/scope); sidebar (search realtime nama/NRP/pangkat/kompi/peleton, seg Semua/Online/Dimonitor/Offline, select Kompi/Peleton, toggle Peta/Daftar); map besar (map-box 600px); drawer detail kanan (overlay) saat klik personel/marker → status device+monitoring, baterai/akurasi/koordinat, Google Maps posisi, dropdown sesi, TIMELINE (🔵 Awal→📍→🟢 Sekarang jika dimonitor / 🔴 Akhir jika selesai). Klik titik → map.setView + popup + Google Maps koordinat aktual (via /history/personnel/{id}). Panel "Kelola Sesi" collapsible (Buat IB/QC modal DIPERTAHANKAN verbatim, Cancel, Export CSV, "Lihat di peta" filter sesi aktif).
- LIVE: SATU setInterval 10 dtk (tick), map dibuat SEKALI (UI.makeMap di render), marker upsert (setLatLng/setStyle/setPopupContent) + hapus marker stale, selectedId dipertahankan lintas poll, detail diupdate via updateDetailLive (tanpa rebuild), route personel terpilih (jika dimonitor) refresh dgn fit:false. Refetch personel tiap 6 poll (~60s) → personel baru muncul dinamis. Page Visibility API: pause saat hidden, refresh saat visible. TIDAK ada location.reload/full reload di monitoring.js. destroy(): clearInterval + remove listener + map.remove().
- Membuka Monitoring TIDAK mengaktifkan GPS (web hanya membaca). Server tetap source of truth (tracking_required, IB/QC/overlap tidak disentuh).
- Endpoint terkonfirmasi terdaftar di backend/api/index.php: /personnel, /devices, /dashboard/locations, /monitoring, /monitoring/{id}/locations, /history/personnel/{id}, /monitoring/{id}/cancel, /reports/monitoring/{id}. Tidak ada API/DB baru.
- Verifikasi statis: `node --check` monitoring.js PASS; ESLint hanya no-undef global browser (Api/UI/L/Pages) = konsisten dgn seluruh /web (bukan bug); brace CSS seimbang; tidak ada referensi method lama (showDetail/refreshDetail) tersisa.
- BELUM DIVERIFIKASI RUNTIME: pod tidak menjalankan PHP/MySQL. Alur browser (login, polling 10s, klik personel, route, Google Maps, IB/QC, cancel, export) diuji user di environment lokal/server.

## Backlog
- P0: Deploy ke cPanel produksi ikut DEPLOYMENT.md (ganti kredensial, HTTPS, ganti password default).
- P0: Build & uji APK pada 5–10 perangkat (pilot) — termasuk app minimized, screen locked, phone restart.
- P1: Cron retensi GPS 90 hari (SQL tersedia di README).
- P2: Import .xlsx native; PDF server-side.

## Next Tasks
1. Deploy produksi + ganti password default semua akun.
2. Pilot 5–10 perangkat, kumpulkan device_events, tuning interval.
3. Deployment penuh setelah pilot stabil.
