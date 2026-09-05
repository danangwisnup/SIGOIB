# SIGoIB Mobile — Panduan Instalasi Lengkap (React Native + TypeScript)

Panduan ini ditulis untuk pemula. Ikuti dari atas ke bawah, jangan melompat.
Target akhir: aplikasi **SIGoIB** ter-install di HP Android (atau emulator) dan bisa aktivasi NRP.

> Estimasi waktu pertama kali: 1–2 jam (sebagian besar mengunduh SDK).
> Untuk iOS wajib memakai **Mac** (dijelaskan di bagian F).

---

## A. Yang Harus Dipasang Dulu (Sekali Saja)

### 1. Node.js (wajib)
- Unduh & install **Node.js LTS** (versi 20 atau 22): https://nodejs.org
- Cek berhasil: buka terminal/cmd, ketik:
  ```bash
  node -v
  npm -v
  ```

### 2. Java JDK 17 (wajib untuk Android)
- Unduh **JDK 17**: https://adoptium.net/temurin/releases/?version=17
- Cek:
  ```bash
  java -version
  ```

### 3. Android Studio + Android SDK (wajib untuk Android)
1. Unduh & install Android Studio: https://developer.android.com/studio
2. Buka Android Studio → **More Actions → SDK Manager**:
   - Tab **SDK Platforms**: centang **Android 14 (API 34)** → Apply.
   - Tab **SDK Tools**: centang **Android SDK Build-Tools**, **Android SDK Platform-Tools**, **Android SDK Command-line Tools**, **Android Emulator** → Apply.
3. Catat lokasi SDK (terlihat di atas SDK Manager), biasanya:
   - Windows: `C:\Users\NAMA\AppData\Local\Android\Sdk`
   - macOS: `/Users/NAMA/Library/Android/sdk`
   - Linux: `/home/NAMA/Android/Sdk`

### 4. Set Environment Variable (wajib)

**Windows** (Cari "Edit the system environment variables" → Environment Variables):
- Tambah variable baru:
  - `ANDROID_HOME` = `C:\Users\NAMA\AppData\Local\Android\Sdk`
- Edit variable `Path`, tambahkan:
  - `%ANDROID_HOME%\platform-tools`
  - `%ANDROID_HOME%\emulator`

**macOS/Linux** — tambahkan ke `~/.zshrc` atau `~/.bashrc`:
```bash
export ANDROID_HOME=$HOME/Library/Android/sdk   # sesuaikan path Anda
export PATH=$PATH:$ANDROID_HOME/platform-tools
export PATH=$PATH:$ANDROID_HOME/emulator
```
Lalu `source ~/.zshrc`.

Cek berhasil (terminal BARU):
```bash
adb version
```

### 5. Siapkan HP atau Emulator (pilih salah satu)

**Opsi A — HP Android fisik (disarankan, GPS asli):**
1. Di HP: **Pengaturan → Tentang Ponsel → tap "Nomor Build" 7x** (aktifkan Developer Mode).
2. **Developer Options → USB Debugging: ON**.
3. Sambungkan HP ke komputer dengan kabel USB.
4. Cek: `adb devices` → harus muncul device Anda. (Di HP, tap "Izinkan" bila ada popup.)

**Opsi B — Emulator:**
- Android Studio → **Device Manager → Create Device** → pilih Pixel 6, system image API 34 → Finish → Play ▶.

---

## B. Menyiapkan Project (5 menit)

Folder `mobile-rn` ini berisi **source code saja** (folder `src/`, `package.json`, dll).
Folder `android/` dan `ios/` belum ada — kita buat otomatis dengan perintah resmi React Native.

### 1. Buka terminal di folder yang nyaman, lalu:

```bash
# 1) Buat project kosong sebagai "rangka" (folder android/ios diambil dari sini)
npx react-native@0.76.5 init SIGoIBShell --version 0.76.5 --skip-install
```

### 2. Salin file rangka ke folder mobile-rn ini:

**Windows (cmd, dari dalam folder mobile-rn):**
```cmd
xcopy ..\SIGoIBShell\android android\ /E /I
xcopy ..\SIGoIBShell\ios ios\ /E /I
copy ..\SIGoIBShell\babel.config.js .
copy ..\SIGoIBShell\metro.config.js .
copy ..\SIGoIBShell\.watchmanconfig .
```

**macOS/Linux (dari dalam folder mobile-rn):**
```bash
cp -r ../SIGoIBShell/android .
cp -r ../SIGoIBShell/ios .
cp ../SIGoIBShell/babel.config.js ../SIGoIBShell/metro.config.js ../SIGoIBShell/.watchmanconfig .
```

> PENTING: jangan menimpa folder `src/` dan `package.json` milik kita.
> Yang disalin HANYA: `android/`, `ios/`, `babel.config.js`, `metro.config.js`, `.watchmanconfig`.

### 3. Install library:

```bash
yarn install
# atau: npm install
```

### 4. Cek source code bebas error:

```bash
npx tsc --noEmit
```
Harus selesai **tanpa output error apa pun**.

---

## C. Satu-Satunya File yang Wajib Anda Edit

### `src/buildConfig.ts` — alamat server API

Buka file `src/buildConfig.ts`, ganti isinya:

```ts
export default {
  // GANTI dengan alamat server Anda:
  API_BASE_URL: 'https://monitoring.domainanda.com',
};
```

Contoh nilai yang benar:
| Kondisi | Nilai API_BASE_URL |
|---|---|
| Server produksi (HTTPS) | `https://monitoring.domainanda.com` |
| Tes lokal — HP fisik satu WiFi dengan komputer | `http://192.168.1.10:8899` (IP komputer Anda) |
| Tes lokal — emulator Android | `http://10.0.2.2:8899` (bukan `localhost`!) |

> Tanpa `/api` di belakang — ditambahkan otomatis oleh aplikasi.
> Tanpa HTTPS, Android 9+ menolak koneksi HTTP kecuali untuk development (lihat bagian G troubleshooting).

---

## D. Menjalankan Aplikasi (Development)

### 1. Jalankan Metro (packager) — terminal 1:
```bash
npx react-native start
```
Biarkan terminal ini terbuka.

### 2. Jalankan ke HP/emulator — terminal 2 (terminal BARU):
```bash
npx react-native run-android
```
Pertama kali butuh 5–15 menit (Gradle mengunduh banyak komponen — normal).

Berhasil jika: aplikasi **SIGoIB** terbuka di HP/emulator, menampilkan layar **AKTIVASI PERANGKAT**.

### 3. Uji alur lengkap:
1. Pastikan server backend jalan dan `API_BASE_URL` benar.
2. Di aplikasi: masukkan **NRP personel** yang sudah terdaftar → **LANJUT**.
3. Muncul "Menunggu persetujuan admin."
4. Di **Web Admin** (web2) → menu **Perangkat** → **SETUJUI** perangkat tersebut.
5. Aplikasi otomatis lanjut ke **PENGATURAN PERANGKAT** → tap **IZINKAN SEMUA** → **TEST PERANGKAT** → **PERANGKAT SIAP** → **MASUK**.
6. Layar utama menampilkan **MENUNGGU MONITORING** (standby) atau **MONITORING AKTIF** saat admin membuat IB/Quick Check.

---

## E. Build APK untuk Dibagikan (Release)

```bash
cd android
# Windows:
gradlew.bat assembleRelease
# macOS/Linux:
./gradlew assembleRelease
```

Hasil APK:
```
android/app/build/outputs/apk/release/app-release.apk
```
Salin file APK ini ke HP personel → install (izinkan "Install dari sumber tidak dikenal").

> Catatan: APK release default memakai signing key debug. Untuk distribusi resmi, buat keystore sendiri (lihat dokumentasi React Native "Signed APK").

---

## F. Build iOS (Hanya di Mac)

Prasyarat: macOS + Xcode + CocoaPods (`sudo gem install cocoapods`).

```bash
cd ios
pod install
cd ..
npx react-native run-ios            # simulator
# Build release: buka ios/Sigoib.xcworkspace di Xcode → Product → Archive
```

Yang sudah disiapkan di source (perlu dipastikan aktif):
- `Info.plist`: izin lokasi (`NSLocationWhenInUse`, `NSLocationAlwaysAndWhenInUse`) + `UIBackgroundModes: location, fetch`.
- Xcode → target app → **Signing & Capabilities** → **+ Background Modes** → centang **Location updates** & **Background fetch**.
- Build release butuh **Apple Developer Account** (berbayar $99/tahun) untuk install ke HP fisik.

---

## G. Troubleshooting (Masalah Paling Umum)

| Gejala | Penyebab & Solusi |
|---|---|
| `SDK location not found` | Buat file `android/local.properties` berisi: `sdk.dir=C:\\Users\\NAMA\\AppData\\Local\\Android\\Sdk` (sesuaikan path) |
| `adb: command not found` | Environment variable `ANDROID_HOME`/`Path` belum diset → ulangi bagian A.4, buka terminal BARU |
| `adb devices` kosong | USB debugging belum ON / kabel data jelek / belum tap "Izinkan" di HP |
| Merah "Unable to load script" | Metro belum jalan → jalankan `npx react-native start` |
| App tidak bisa konek server (dev, HTTP) | Emulator: pakai `10.0.2.2`, bukan `localhost`. HP fisik: pakai IP LAN komputer & satu WiFi |
| Error license SDK | Jalankan: `yes \| sdkmanager --licenses` (atau via Android Studio SDK Manager) |
| Gradle sangat lama pertama kali | Normal — mengunduh ratusan MB. Berikutnya cepat |
| `npx tsc` error | Jalankan `yarn install` dulu |
| iOS `pod install` gagal | `cd ios && pod repo update && pod install` |

---

## H. Cara Kerja Aplikasi (Ringkas)

- **Tidak ada login/password.** Identitas = NRP + perangkat (hardware ID stabil). Reinstall di HP yang sama tetap dikenali server.
- **Server yang memutuskan tracking.** Admin membuat IB/Quick Check di web → aplikasi otomatis mulai tracking (GPS ~30 detik). Selesai → otomatis standby (GPS mati). Tidak ada tombol start/stop.
- **Internet mati** → lokasi tetap direkam ke SQLite di HP → otomatis dikirim batch saat internet kembali (tanpa duplikat).
- **Device diganti** → admin revoke perangkat lama di web, HP baru registrasi → admin approve.
- **Background**: Android memakai foreground service (notifikasi persisten). Setelah force-stop/reboot, aplikasi tidak otomatis hidup lagi — batasan OS Android/iOS, bukan bug.
