# SIGoIB Mobile — React Native + TypeScript

Client perangkat personel. Sederhana: aktivasi NRP, setup permission satu kali, layar status tracking/standby. Server = source of truth; tidak ada tombol start/stop tracking.

## Struktur

```
src/
├── api/          # client HTTP ke API existing
├── auth/         # (token via storage/secure — Keychain/Keystore)
├── device/       # hardware-stable device_uuid, registrasi NRP
├── tracking/     # tracking controller (server-driven)
├── background/   # foreground service Android (react-native-background-actions)
├── storage/      # secure token + SQLite offline queue
├── permissions/  # checklist permission satu kali
├── screens/      # Activation / Setup / Home / Revoked
├── components/   # StatusPill
├── services/     # device events, UI state
├── types/        # kontrak response API
└── utils/        # waktu, client_point_id
```

## Prinsip

- `device_uuid` = **hardware ID stabil** (Android ID / `identifierForVendor`) — reinstall di perangkat yang sama dikenali server dan mendapat token baru (bukan terkunci); perangkat berbeda menghasilkan uuid berbeda sehingga tetap lewat approval.
- STANDBY: GPS OFF, polling status ~60 dtk. TRACKING: GPS tiap ~30 dtk (dari server), batch sync 5–10 titik.
- Offline → SQLite queue (`client_point_id` idempotency) → batch sync saat online kembali; data lokal dihapus hanya setelah server konfirmasi.
- Token di Keychain/Keystore; jika server menolak token (401/403) → tracking berhenti, token dihapus, tampil PERANGKAT DICABUT.

## Setup Project (mesin dengan Node + RN toolchain)

```bash
npx react-native@latest init SIGoIB --version 0.76.5 --directory temp-init
# salin folder android/ ios/ dari hasil init ke folder ini (atau init di folder ini lalu timpa dengan src/ repo)
cp -r temp-init/android temp-init/ios temp-init/.eslintrc.js temp-init/babel.config.js temp-init/metro.config.js .
yarn install
npx tsc --noEmit   # typecheck
npx react-native run-android
npx react-native run-ios     # macOS
```

## API Base URL

Edit `src/buildConfig.ts` (`API_BASE_URL`) ke server produksi HTTPS, mis. `https://monitoring.domainanda`.

## Konfigurasi Android

Tambahkan ke `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION"/>
<uses-permission android:name="android.permission.FOREGROUND_SERVICE"/>
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION"/>
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
<uses-permission android:name="android.permission.WAKE_LOCK"/>
```

Foreground service dari `react-native-background-actions` menampilkan notifikasi persisten (aturan Android 8+). Pandu user untuk pengecualian battery optimization (vendor Xiaomi/Oppo agresif). Tracking tidak dijamin hidup setelah force-stop/reboot — batasan OS.

## Konfigurasi iOS

`ios/SIGoIB/Info.plist`:

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>Aplikasi memerlukan lokasi untuk monitoring IB dan Quick Check.</string>
<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>Aplikasi memerlukan lokasi di background selama sesi monitoring aktif.</string>
<key>UIBackgroundModes</key>
<array>
    <string>location</string>
    <string>fetch</string>
</array>
```

Xcode → Signing & Capabilities → aktifkan **Background Modes** (Location updates + Background fetch). Build release memerlukan Apple Developer account.

## Batasan (sesuai kemampuan OS)

- Tidak menjanjikan tracking setelah force-stop / OS termination / reboot / permission dicabut.
- iOS background location bergantung izin "Always" dan kebijakan OS.
