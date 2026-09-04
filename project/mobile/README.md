# Mobile App (Flutter) — Monitoring IB & Quick Check

Client perangkat personel. Sangat sederhana: tanpa bottom navigation, tanpa map, tanpa tombol Start/Stop Tracking.

## Layar

1. **Aktivasi Perangkat** — input NRP → server cek → PENDING ("Menunggu persetujuan admin") → auto-poll tiap 15 dtk → APPROVED (terima device token) → lanjut.
2. **Pengaturan Perangkat** — cek izin Lokasi, Background Location, Notifikasi, Battery Optimization + TEST PERANGKAT (GPS/Internet/Server/Permission) → PERANGKAT SIAP.
3. **Home** — MONITORING AKTIF (hijau, nama session) / STANDBY (abu). Indikator GPS, Internet, Battery, update terakhir.

## Prinsip

- Server menentukan `tracking_required`; app hanya mengikuti.
- STANDBY: GPS OFF, polling status ~60 dtk.
- TRACKING: GPS tiap ~30 dtk (dari server), buffer SQLite, batch sync 5–10 point.
- Offline: queue di SQLite (`client_point_id` untuk idempotency); sync saat online kembali.
- Device revoked → app berhenti mengirim GPS, tampil "Perangkat nonaktif".
- Device token disimpan di secure storage (Keychain/EncryptedSharedPreferences).

## Build

Folder ini hanya berisi source (lib/, pubspec.yaml, AndroidManifest.xml, Info.plist).
Lengkapi project platform sekali saja:

```bash
flutter create --org id.monitoring --project-name monitoring_ib .
# Pilih YA saat diminta menimpa? TIDAK — biarkan file dari repo ini menang:
# pastikan lib/, pubspec.yaml, android/app/src/main/AndroidManifest.xml,
# ios/Runner/Info.plist tetap versi repo ini.
flutter pub get
flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000
flutter build apk --dart-define=API_BASE_URL=https://server-anda.example.com
```

## Catatan Platform

- **Android**: foreground service + notifikasi persisten (`FOREGROUND_SERVICE_LOCATION`), izin `ACCESS_BACKGROUND_LOCATION`, battery optimization handling. Tracking tidak dijamin hidup setelah force-stop/uninstall — batasan OS.
- **iOS**: background mode `location` + `fetch`, izin Always. iOS dapat menghentikan update sesuai kebijakan OS.
